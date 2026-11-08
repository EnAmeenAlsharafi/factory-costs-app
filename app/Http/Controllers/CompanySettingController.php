<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanySettingRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanySettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('settings.company', ['company' => CompanySetting::current()]);
    }

    public function update(CompanySettingRequest $request): RedirectResponse
    {
        $company = CompanySetting::current();
        $data = collect($request->validated())->except(['logo', 'remove_logo'])->all();

        if ($request->hasFile('logo')) {
            $this->deleteLogo($company);
            $data['logo_path'] = $request->file('logo')->store('company', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($company);
            $data['logo_path'] = null;
        }

        $company->update($data);

        return redirect()->route('settings.company.edit')->with('success', 'تم حفظ إعدادات الشركة.');
    }

    public function logo(): BinaryFileResponse
    {
        $path = CompanySetting::current()->logoFilePath();
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=604800']);
    }

    private function deleteLogo(CompanySetting $company): void
    {
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }
    }
}
