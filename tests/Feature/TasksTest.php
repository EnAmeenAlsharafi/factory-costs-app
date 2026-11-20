<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Permission;
use App\Models\PersonalNote;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskRequest;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $carpenter;

    private User $secondCarpenter;

    private User $foamWorker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create([
            'role_id' => Role::where('name', 'production_manager')->value('id'),
            'is_active' => true,
        ]);
        $this->carpenter = $this->worker('CARPENTRY');
        $this->secondCarpenter = $this->worker('CARPENTRY');
        $this->foamWorker = $this->worker('FOAM');
    }

    public function test_an_employee_keeps_a_personal_task_nobody_else_can_open(): void
    {
        $this->actingAs($this->carpenter)
            ->post(route('tasks.store'), ['title' => 'تجهيز قائمة الأخشاب', 'my_day' => 1])
            ->assertSessionHasNoErrors();

        $task = Task::where('title', 'تجهيز قائمة الأخشاب')->firstOrFail();
        $this->assertFalse($task->is_assigned);
        $this->assertTrue($task->assignees()->sole()->isInMyDay());

        $this->actingAs($this->carpenter)->get(route('tasks.index', ['view' => 'my-day']))->assertOk()->assertSee('تجهيز قائمة الأخشاب');
        $this->actingAs($this->manager)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_an_employee_without_the_permission_cannot_assign_tasks(): void
    {
        $this->actingAs($this->carpenter)
            ->post(route('tasks.store'), ['title' => 'مهمة', 'assignee_ids' => [$this->secondCarpenter->id]])
            ->assertSessionHasErrors('assignee_ids');

        $this->actingAs($this->carpenter)->get(route('tasks.create'))->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_department_scope_reaches_only_the_assigners_own_department(): void
    {
        $supervisor = $this->worker('CARPENTRY');
        $supervisor->role->permissions()->attach(Permission::where('name', 'tasks.assign.department')->value('id'));
        $supervisor->refresh();

        $this->actingAs($supervisor)
            ->post(route('tasks.store'), ['title' => 'خارج القسم', 'assignee_ids' => [$this->foamWorker->id]])
            ->assertSessionHasErrors('assignee_ids');

        $this->actingAs($supervisor)
            ->post(route('tasks.store'), ['title' => 'داخل القسم', 'assignee_ids' => [$this->carpenter->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->carpenter->id], Task::where('title', 'داخل القسم')->sole()->assignees()->pluck('user_id')->all());
    }

    public function test_assigning_to_several_people_needs_the_multiple_permission(): void
    {
        $supervisor = $this->worker('CARPENTRY');
        $supervisor->role->permissions()->attach(Permission::where('name', 'tasks.assign.department')->value('id'));
        $supervisor->refresh();

        $this->actingAs($supervisor)
            ->post(route('tasks.store'), ['title' => 'جرد', 'assignee_ids' => [$this->carpenter->id, $this->secondCarpenter->id]])
            ->assertSessionHasErrors('assignee_ids');
    }

    public function test_assigning_a_whole_department_gives_each_member_their_own_copy_and_notification(): void
    {
        $carpentry = Department::where('code', 'CARPENTRY')->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('tasks.store'), ['title' => 'جرد مخزن الأخشاب', 'due_date' => today()->addDays(3)->toDateString(), 'department_ids' => [$carpentry->id]])
            ->assertSessionHasNoErrors();

        $task = Task::where('title', 'جرد مخزن الأخشاب')->sole();
        $this->assertTrue($task->is_assigned);
        $carpentryIds = User::where('department_id', $carpentry->id)->where('is_active', true)->pluck('id')->sort()->values()->all();
        $this->assertContains($this->carpenter->id, $carpentryIds);
        $this->assertEqualsCanonicalizing($carpentryIds, $task->assignees()->pluck('user_id')->all());
        $this->assertEqualsCanonicalizing($carpentryIds, $this->recipientsOf(NotificationType::TodoAssigned));
        $this->assertNotContains($this->foamWorker->id, $task->assignees()->pluck('user_id')->all());
    }

    public function test_each_assignee_finishes_on_their_own_and_the_author_is_told(): void
    {
        $task = $this->assign([$this->carpenter, $this->secondCarpenter]);
        $carpenterCopy = $task->assignees()->where('user_id', $this->carpenter->id)->sole();

        $this->actingAs($this->carpenter)
            ->patch(route('tasks.assignments.status', $carpenterCopy), ['status' => TaskAssignee::STATUS_DONE])
            ->assertSessionHasNoErrors();

        $this->assertTrue($carpenterCopy->fresh()->isDone());
        $this->assertSame(TaskAssignee::STATUS_OPEN, $task->assignees()->where('user_id', $this->secondCarpenter->id)->value('status'));
        $this->assertSame([$this->manager->id], $this->recipientsOf(NotificationType::TodoCompleted));

        $this->actingAs($this->secondCarpenter)
            ->patch(route('tasks.assignments.status', $carpenterCopy), ['status' => TaskAssignee::STATUS_OPEN])
            ->assertForbidden();
    }

    public function test_an_approved_extension_moves_only_that_employees_due_date(): void
    {
        $task = $this->assign([$this->carpenter, $this->secondCarpenter], today()->addDay()->toDateString());
        $carpenterCopy = $task->assignees()->where('user_id', $this->carpenter->id)->sole();
        $newDate = today()->addDays(5)->toDateString();

        $this->actingAs($this->carpenter)
            ->post(route('tasks.requests.store', $carpenterCopy), ['type' => TaskRequest::TYPE_EXTEND, 'reason' => 'بانتظار الخامات', 'requested_due_date' => $newDate])
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->manager->id], $this->recipientsOf(NotificationType::TodoRequestSubmitted));

        $request = $carpenterCopy->requests()->sole();
        $this->actingAs($this->carpenter)
            ->post(route('tasks.requests.decide', $request), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('tasks.requests.decide', $request), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame($newDate, $carpenterCopy->fresh()->due_date->toDateString());
        $this->assertSame(today()->addDay()->toDateString(), $task->assignees()->where('user_id', $this->secondCarpenter->id)->sole()->due_date->toDateString());
        $this->assertSame([$this->carpenter->id], $this->recipientsOf(NotificationType::TodoRequestDecided));
    }

    public function test_an_assignee_cannot_send_a_second_request_while_one_is_pending(): void
    {
        $copy = $this->assign([$this->carpenter])->assignees()->sole();
        TaskRequest::factory()->create(['task_assignee_id' => $copy->id]);

        $this->actingAs($this->carpenter)
            ->post(route('tasks.requests.store', $copy), ['type' => TaskRequest::TYPE_DECLINE, 'reason' => 'مشغول'])
            ->assertSessionHasErrors('reason');
    }

    public function test_an_approved_decline_takes_the_task_off_the_employees_lists(): void
    {
        $copy = $this->assign([$this->carpenter])->assignees()->sole();
        $request = TaskRequest::factory()->create(['task_assignee_id' => $copy->id]);

        $this->actingAs($this->manager)->post(route('tasks.requests.decide', $request), ['decision' => 'approve']);

        $this->assertTrue($copy->fresh()->isDeclined());
        $this->assertNotListed($this->carpenter, $copy->task);
    }

    public function test_a_refused_decline_leaves_the_task_with_the_employee(): void
    {
        $copy = $this->assign([$this->carpenter])->assignees()->sole();
        $request = TaskRequest::factory()->create(['task_assignee_id' => $copy->id]);

        $this->actingAs($this->manager)->post(route('tasks.requests.decide', $request), ['decision' => 'reject']);

        $this->assertSame(TaskRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame(TaskAssignee::STATUS_OPEN, $copy->fresh()->status);
    }

    public function test_only_the_author_edits_a_task_and_a_new_date_skips_finished_copies(): void
    {
        $task = $this->assign([$this->carpenter, $this->secondCarpenter], today()->addDay()->toDateString());
        $task->assignees()->where('user_id', $this->carpenter->id)->update(['status' => TaskAssignee::STATUS_DONE, 'completed_at' => now()]);
        $newDate = today()->addDays(10)->toDateString();
        $payload = ['title' => $task->title, 'due_date' => $newDate, 'assignee_ids' => [$this->carpenter->id, $this->secondCarpenter->id]];

        $this->actingAs($this->carpenter)->put(route('tasks.update', $task), $payload)->assertForbidden();
        $this->actingAs($this->manager)->put(route('tasks.update', $task), $payload)->assertSessionHasNoErrors();

        $this->assertSame(today()->addDay()->toDateString(), $task->assignees()->where('user_id', $this->carpenter->id)->sole()->due_date->toDateString());
        $this->assertSame($newDate, $task->assignees()->where('user_id', $this->secondCarpenter->id)->sole()->due_date->toDateString());
    }

    public function test_a_cancelled_task_disappears_from_the_assignees_lists(): void
    {
        $task = $this->assign([$this->carpenter]);

        $this->actingAs($this->manager)->delete(route('tasks.destroy', $task))->assertRedirect();

        $this->assertTrue($task->fresh()->isCancelled());
        $this->assertNotListed($this->carpenter, $task);
    }

    public function test_notes_are_private_to_their_author(): void
    {
        $note = PersonalNote::factory()->create(['user_id' => $this->carpenter->id, 'body' => 'رقم المورد 0500000000']);

        $this->actingAs($this->carpenter)->get(route('tasks.index', ['view' => 'notes']))->assertSee('رقم المورد 0500000000');
        $this->actingAs($this->manager)->get(route('tasks.index', ['view' => 'notes']))->assertDontSee('رقم المورد 0500000000');
        $this->actingAs($this->manager)->delete(route('tasks.notes.destroy', $note))->assertForbidden();
    }

    public function test_the_scan_reminds_once_on_the_due_date(): void
    {
        $this->assign([$this->carpenter], today()->toDateString());

        $this->artisan('notifications:scan')->assertSuccessful();
        $this->artisan('notifications:scan')->assertSuccessful();

        $this->assertSame(1, Notification::ofType(NotificationType::TodoDueToday)->count());
        $this->assertSame([$this->carpenter->id], $this->recipientsOf(NotificationType::TodoDueToday));
    }

    /**
     * @param  list<User>  $assignees
     */
    private function assign(array $assignees, ?string $dueDate = null): Task
    {
        $this->actingAs($this->manager)
            ->post(route('tasks.store'), [
                'title' => 'مهمة اختبار '.uniqid(),
                'due_date' => $dueDate,
                'assignee_ids' => array_map(fn (User $user) => $user->id, $assignees),
            ])
            ->assertSessionHasNoErrors();

        return Task::latest('id')->firstOrFail();
    }

    /**
     * The task is in none of the employee's lists (its title may still appear in their notifications).
     */
    private function assertNotListed(User $employee, Task $task): void
    {
        $this->actingAs($employee)->get(route('tasks.index', ['view' => 'assigned-to-me']))
            ->assertOk()
            ->assertViewHas('openItems', fn ($items) => $items->doesntContain('task_id', $task->id))
            ->assertViewHas('counts', fn (array $counts) => $counts['assigned-to-me'] === 0);
    }

    private function worker(string $departmentCode): User
    {
        $department = Department::where('code', $departmentCode)->firstOrFail();

        return User::factory()->create([
            'role_id' => Role::where('department_id', $department->id)->where('name', 'like', '%worker')->value('id'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return list<int>
     */
    private function recipientsOf(NotificationType $type): array
    {
        return NotificationRecipient::whereIn('notification_id', Notification::ofType($type)->select('id'))
            ->pluck('user_id')->sort()->values()->all();
    }
}
