<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImageTemplateSettingsController extends Controller
{
    private const SETTING_KEY = 'branding.image_template_path';

    /**
     * The border/logo overlay ImageOptimizer stamps onto every admin-cropped
     * product and variant photo. Null means no template is configured yet,
     * in which case ImageOptimizer leaves photos untouched.
     */
    public static function path(): ?string
    {
        return AppSetting::get(self::SETTING_KEY);
    }

    public function index(): View
    {
        $path = self::path();

        return view('admin.image-template-settings.index', [
            'templateUrl' => $path ? Storage::disk('public')->url($path) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template' => ['required', 'file', 'image', 'mimes:png', 'max:5120'],
        ]);

        $oldPath = self::path();

        $path = $validated['template']->storeAs(
            'branding',
            Str::random(40).'.png',
            'public'
        );

        AppSetting::set(self::SETTING_KEY, $path);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        AuditLog::record('Image Template Updated', 'Product/variant photo overlay template replaced.');

        return back()->with('status', 'Image template saved — it will be stamped onto every photo cropped and uploaded from now on.');
    }

    public function destroy(): RedirectResponse
    {
        $oldPath = self::path();

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        AppSetting::set(self::SETTING_KEY, null);

        AuditLog::record('Image Template Removed', 'Product/variant photos will no longer get a logo/border overlay.');

        return back()->with('status', 'Image template removed.');
    }
}
