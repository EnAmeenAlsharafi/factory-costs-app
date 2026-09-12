<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of users with search and filter capabilities.
     */
    public function index(Request $request): View
    {
        abort_if(! $request->user()->can('users.view'), 403, 'غير مصرح لك بعرض سجل المستخدمين.');

        $users = User::with(['role', 'department'])
            ->filter($request->only(['search', 'role_id', 'status']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(Request $request): View
    {
        abort_if(! $request->user()->can('users.create'), 403, 'غير مصرح لك بإضافة مستخدم جديد.');

        $roles = Role::orderBy('display_name')->get();
        $departments = Department::active()->orderBy('sort_order')->get();

        return view('users.create', compact('roles', 'departments'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'role_id' => $request->validated('role_id'),
            'department_id' => $request->validated('department_id'),
            'password' => Hash::make($request->validated('password')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('users.index')->with('success', 'تم إنشاء حساب المستخدم بنجاح.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(Request $request, User $user): View
    {
        abort_if(! $request->user()->can('users.update'), 403, 'غير مصرح لك بتعديل بيانات المستخدمين.');

        $roles = Role::orderBy('display_name')->get();
        $departments = Department::orderBy('sort_order')->get();

        return view('users.edit', compact('user', 'roles', 'departments'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'role_id' => $request->validated('role_id'),
            'department_id' => $request->validated('department_id'),
        ];

        // Guard against an administrator disabling their own account
        if ($user->id === Auth::id() && ! $request->boolean('is_active')) {
            return back()->with('error', 'لا يمكنك إلغاء تفعيل حسابك الحالي أثناء تسجيل دخولك.');
        }

        $data['is_active'] = $request->boolean('is_active');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $action = $user->is_active ? 'users.deactivate' : 'users.activate';
        abort_if(! $request->user()->can($action), 403, 'غير مصرح لك بتغيير حالة تفعيل المستخدم.');

        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك تغيير حالة تفعيل حسابك الشخصي.');
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $message = $user->is_active ? 'تم تفعيل حساب المستخدم بنجاح.' : 'تم تعطيل حساب المستخدم بنجاح.';

        return back()->with('success', $message);
    }
}
