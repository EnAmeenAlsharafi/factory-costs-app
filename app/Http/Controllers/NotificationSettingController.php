<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Notifications\AudienceResolver;
use App\Notifications\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The matrix that decides which audience of which notification is switched on.
 */
class NotificationSettingController extends Controller
{
    public function edit(Request $request, AudienceResolver $resolver): View
    {
        abort_unless($request->user()->can('notifications.manage'), 403);

        $disabled = NotificationSetting::disabledMap();

        $rows = collect(NotificationType::cases())
            ->map(fn (NotificationType $type) => [
                'type' => $type,
                'audiences' => collect($type->audiences())->map(fn (string $audience) => [
                    'key' => $audience,
                    'label' => $this->audienceLabel($audience),
                    'enabled' => ! isset($disabled[$type->value.'|'.$audience]),
                    'reach' => $audience === 'department' || $audience === 'user'
                        ? null
                        : count($resolver->resolve([$audience])),
                ])->all(),
            ])
            ->groupBy(fn (array $row) => $row['type']->group());

        return view('notifications.settings', ['groups' => $rows]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.manage'), 403);

        $enabled = collect($request->input('enabled', []))->keys();

        foreach (NotificationType::cases() as $type) {
            foreach ($type->audiences() as $audience) {
                $key = $type->value.'|'.$audience;
                NotificationSetting::updateOrCreate(
                    ['type' => $type->value, 'audience_key' => $audience],
                    ['is_enabled' => $enabled->contains($key)],
                );
            }
        }

        return back()->with('success', 'تم حفظ إعدادات الإشعارات.');
    }

    private function audienceLabel(string $audience): string
    {
        return match (true) {
            $audience === 'user' => 'الموظف المعني',
            $audience === 'department' => 'القسم المعني',
            str_starts_with($audience, 'role:') => 'دور: '.substr($audience, 5),
            str_starts_with($audience, 'permission:') => 'من يملك صلاحية: '.substr($audience, 11),
            default => $audience,
        };
    }
}
