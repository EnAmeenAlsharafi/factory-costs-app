<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationSetting;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\AudienceResolver;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use App\Services\ProductionAssignmentService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $carpenter;

    private User $foamWorker;

    private ProductionOrder $productionOrder;

    private ProductionOrderService $production;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create([
            'role_id' => Role::where('name', 'production_manager')->value('id'),
            'is_active' => true,
        ]);
        $this->carpenter = $this->worker('CARPENTRY');
        $this->foamWorker = $this->worker('FOAM');

        $this->production = app(ProductionOrderService::class);

        $order = CustomerOrder::create([
            'order_number' => 'ORD-NOTIF-0001',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 500,
            'created_by_user_id' => $this->manager->id,
        ]);
        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-NOTIF', 'name' => 'Notif Recipe', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'approved_recipe_version_id' => $version->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 2,
            'unit_price' => 250,
            'line_total' => 500,
        ]);

        $this->productionOrder = $this->production->releaseProductionOrder(
            $this->production->createFromOrderLine($line, ['released_quantity' => 2]),
            ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail(),
            $this->manager,
        );
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

    private function operation(string $code): ProductionOrderOperation
    {
        return ProductionOrderOperation::where('production_order_id', $this->productionOrder->id)
            ->where('operation_code', $code)->firstOrFail();
    }

    private function recipientsOf(NotificationType $type): array
    {
        return NotificationRecipient::whereIn('notification_id', Notification::ofType($type)->select('id'))
            ->pluck('user_id')->sort()->values()->all();
    }

    public function test_releasing_an_order_tells_only_the_department_of_its_first_stage(): void
    {
        $recipients = $this->recipientsOf(NotificationType::ProductionOrderReleased);

        $this->assertContains($this->carpenter->id, $recipients);
        $this->assertNotContains($this->foamWorker->id, $recipients, 'A stage that has not reached Foam must not notify Foam.');
    }

    public function test_the_person_who_acted_is_never_told_about_their_own_action(): void
    {
        $this->assertNotContains(
            $this->manager->id,
            $this->recipientsOf(NotificationType::ProductionOrderReleased),
            'The manager who released the order must not be notified of their own release.',
        );
    }

    public function test_finishing_a_stage_tells_the_next_department_only(): void
    {
        app(ProductionExecutionService::class)->complete($this->operation('OP_CARPENTRY'), $this->carpenter);

        $recipients = $this->recipientsOf(NotificationType::StageReady);

        $this->assertContains($this->foamWorker->id, $recipients);
        $this->assertNotContains($this->carpenter->id, $recipients);
    }

    public function test_assigning_a_task_tells_that_employee_alone(): void
    {
        app(ProductionAssignmentService::class)->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);

        $this->assertSame([$this->carpenter->id], $this->recipientsOf(NotificationType::TaskAssigned));
    }

    public function test_reassigning_a_task_tells_the_new_employee_and_the_previous_one(): void
    {
        $assignments = app(ProductionAssignmentService::class);
        $otherCarpenter = $this->worker('CARPENTRY');
        $assignments->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);

        $assignments->assign($this->operation('OP_CARPENTRY')->fresh(), $otherCarpenter, $this->manager);

        $this->assertSame(
            collect([$this->carpenter->id, $otherCarpenter->id])->sort()->values()->all(),
            $this->recipientsOf(NotificationType::TaskReassigned),
        );
    }

    public function test_blocking_a_stage_tells_the_department_and_production_management(): void
    {
        app(ProductionExecutionService::class)->block($this->operation('OP_CARPENTRY'), 'MATERIAL_SHORTAGE', 'نقص ألواح', $this->manager);

        $recipients = $this->recipientsOf(NotificationType::StageBlocked);

        $this->assertContains($this->carpenter->id, $recipients);
        $this->assertNotContains($this->foamWorker->id, $recipients);
        $notification = Notification::ofType(NotificationType::StageBlocked)->firstOrFail();
        $this->assertSame('danger', $notification->severity);
        $this->assertStringContainsString('نقص مواد', $notification->body_ar);
    }

    public function test_an_administrator_is_not_a_recipient_of_permission_addressed_notifications(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_active' => true]);

        $resolved = app(AudienceResolver::class)->resolve(['permission:production.release']);

        $this->assertNotContains($admin->id, $resolved, 'The admin role holds every permission; it must not receive every notification.');
        $this->assertContains($this->manager->id, $resolved);
    }

    public function test_an_inactive_employee_receives_nothing(): void
    {
        $this->carpenter->update(['is_active' => false]);

        app(NotificationService::class)->raise(NotificationType::StageReady, [
            'title' => 'مرحلة جاهزة',
            'department_id' => Department::where('code', 'CARPENTRY')->value('id'),
        ]);

        $this->assertNotContains($this->carpenter->id, $this->recipientsOf(NotificationType::StageReady));
    }

    public function test_switching_an_audience_off_stops_that_notification(): void
    {
        NotificationSetting::create([
            'type' => NotificationType::StageBlocked->value,
            'audience_key' => 'department',
            'is_enabled' => false,
        ]);

        app(ProductionExecutionService::class)->block($this->operation('OP_CARPENTRY'), 'MATERIAL_SHORTAGE', null, $this->manager);

        $this->assertNotContains($this->carpenter->id, $this->recipientsOf(NotificationType::StageBlocked));
    }

    public function test_the_scheduled_scan_announces_a_late_stage_once_a_day(): void
    {
        $operation = $this->operation('OP_CARPENTRY');
        $operation->update([
            'ready_at' => now()->subDays(20),
            'target_days' => 2,
            'due_at' => now()->subDays(10),
        ]);

        $this->artisan('notifications:scan')->assertSuccessful();
        $this->artisan('notifications:scan')->assertSuccessful();

        $this->assertSame(1, Notification::ofType(NotificationType::StageOverdue)->count());
        $this->assertContains($this->carpenter->id, $this->recipientsOf(NotificationType::StageOverdue));
        $this->assertSame('SYSTEM', Notification::ofType(NotificationType::StageOverdue)->value('source'));
    }

    public function test_a_long_overdue_stage_escalates_to_production_management(): void
    {
        $this->operation('OP_CARPENTRY')->update([
            'ready_at' => now()->subDays(20),
            'target_days' => 2,
            'due_at' => now()->subDays(10),
        ]);

        $this->artisan('notifications:scan')->assertSuccessful();

        $this->assertContains($this->manager->id, $this->recipientsOf(NotificationType::StageOverdue));
    }

    public function test_an_action_that_rolls_back_notifies_nobody(): void
    {
        $before = Notification::count();

        try {
            DB::transaction(function () {
                app(ProductionAssignmentService::class)->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);

                throw new \RuntimeException('something later in the action failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, Notification::count());
    }

    public function test_an_employee_sees_their_own_notifications_and_can_clear_them(): void
    {
        app(ProductionAssignmentService::class)->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);

        $this->actingAs($this->carpenter)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('أُسندت إليك مهمة جديدة');

        // Two: the release reached the department, the assignment reached the carpenter.
        $this->actingAs($this->carpenter)->getJson(route('notifications.unread-count'))
            ->assertOk()->assertJsonPath('count', 2);

        $this->actingAs($this->carpenter)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, app(NotificationService::class)->unreadCount($this->carpenter));
    }

    public function test_one_employee_cannot_read_another_employees_notification(): void
    {
        app(ProductionAssignmentService::class)->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);
        $recipient = NotificationRecipient::forUser($this->carpenter)->firstOrFail();

        $this->actingAs($this->foamWorker)->post(route('notifications.read', $recipient))->assertForbidden();

        $this->assertNull($recipient->fresh()->read_at);
    }

    public function test_only_authorised_staff_reach_announcements_and_settings(): void
    {
        $this->actingAs($this->carpenter)->get(route('notifications.announce.create'))->assertForbidden();
        $this->actingAs($this->carpenter)->get(route('settings.notifications.edit'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('notifications.announce.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('settings.notifications.edit'))->assertForbidden();
    }

    public function test_an_announcement_reaches_the_chosen_department_only(): void
    {
        $this->actingAs($this->manager)->post(route('notifications.announce.store'), [
            'title' => 'اجتماع قسم النجارة الساعة 9',
            'target' => 'department',
            'department_id' => Department::where('code', 'CARPENTRY')->value('id'),
        ])->assertRedirect(route('notifications.index'));

        $recipients = $this->recipientsOf(NotificationType::Announcement);

        $this->assertContains($this->carpenter->id, $recipients);
        $this->assertNotContains($this->foamWorker->id, $recipients);
        $this->assertSame('MANUAL', Notification::ofType(NotificationType::Announcement)->value('source'));
    }

    public function test_an_announcement_to_named_employees_reaches_only_them(): void
    {
        $this->actingAs($this->manager)->post(route('notifications.announce.store'), [
            'title' => 'راجع مواصفات الطلب قبل البدء',
            'target' => 'users',
            'user_ids' => [$this->foamWorker->id],
        ])->assertRedirect();

        $this->assertSame([$this->foamWorker->id], $this->recipientsOf(NotificationType::Announcement));
    }

    public function test_an_announcement_without_an_audience_is_rejected(): void
    {
        $this->actingAs($this->manager)->post(route('notifications.announce.store'), [
            'title' => 'إعلان بلا جمهور',
            'target' => 'department',
        ])->assertSessionHasErrors('department_id');

        $this->assertSame(0, Notification::ofType(NotificationType::Announcement)->count());
    }

    public function test_settings_matrix_saves_the_switches(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_active' => true]);

        $this->actingAs($admin)->put(route('settings.notifications.update'), [
            'enabled' => [NotificationType::StageReady->value.'|department' => '1'],
        ])->assertRedirect();

        $this->assertTrue(NotificationSetting::where('type', NotificationType::StageReady->value)
            ->where('audience_key', 'department')->value('is_enabled'));
        $this->assertFalse(NotificationSetting::where('type', NotificationType::TaskAssigned->value)
            ->where('audience_key', 'user')->value('is_enabled'));
    }

    public function test_a_failing_notification_never_breaks_the_action_that_caused_it(): void
    {
        // A department that does not exist: writing the notification fails, the operation is untouched.
        $operation = $this->operation('OP_CARPENTRY');

        $notification = app(NotificationService::class)->raise(NotificationType::TaskAssigned, [
            'title' => 'مهمة',
            'users' => [$this->carpenter->id],
            'department_id' => 999999,
        ]);

        $this->assertNull($notification);
        $this->assertSame(0, Notification::ofType(NotificationType::TaskAssigned)->count());
        $this->assertSame('READY', $operation->fresh()->status);
    }

    public function test_pruning_removes_what_nobody_needs_any_more(): void
    {
        app(ProductionAssignmentService::class)->assign($this->operation('OP_CARPENTRY'), $this->carpenter, $this->manager);
        NotificationRecipient::query()->update(['read_at' => now()->subDays(90)]);

        app(NotificationService::class)->prune();

        $this->assertSame(0, NotificationRecipient::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_an_unpaid_order_blocked_at_release_tells_receivables(): void
    {
        config(['app.enforce_payment_gates' => true]);
        $receivables = User::factory()->create([
            'role_id' => Role::where('name', 'receivables_user')->value('id'),
            'is_active' => true,
        ]);
        $order = CustomerOrder::create([
            'order_number' => 'ORD-NOTIF-UNPAID',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'payment_terms_type' => 'FULL_BEFORE_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 900,
            'created_by_user_id' => $this->manager->id,
        ]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 900,
            'line_total' => 900,
            'approved_recipe_version_id' => ManufacturingRecipeVersion::value('id'),
        ]);

        try {
            $this->production->releaseProductionOrder(
                $this->production->createFromOrderLine($line, ['released_quantity' => 1]),
                ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail(),
                $this->manager,
            );
            $this->fail('An unpaid order must not reach the shop floor.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertContains($receivables->id, $this->recipientsOf(NotificationType::OrderBlockedByPayment));
    }
}
