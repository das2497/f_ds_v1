<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Html\DescriptionSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Receives the Apps Script `onEdit` webhook fired when an admin hand-edits
 * a row in the Products/Variants Google Sheet. Only the columns each sheet
 * is allowed to edit are applied — every other column in the posted row
 * (ID, SKU, Source Vendor, ...) is read-only and silently ignored, so the
 * next DB→Sheet push just corrects it back.
 */
class SheetWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sheet' => ['required', 'in:Products,Variants'],
            'id' => ['required', 'integer'],
            'values' => ['required', 'array'],
        ]);

        // Sheet cells send '' for anything left blank; treat that as "no
        // value" rather than failing numeric/enum validation on an empty
        // string.
        $values = array_map(fn ($value) => $value === '' ? null : $value, $validated['values']);

        return $validated['sheet'] === 'Products'
            ? $this->updateProduct($validated['id'], $values)
            : $this->updateVariant($validated['id'], $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updateProduct(int $id, array $values): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['error' => "No product with ID {$id}. Add new products from the app, not by adding a blank row."], 404);
        }

        try {
            $data = validator($values, [
                'Title' => ['required', 'string', 'max:255'],
                'Description' => ['nullable', 'string'],
                'Price' => ['required', 'numeric', 'min:0'],
                'Active' => ['required'],
            ])->validate();
        } catch (ValidationException $e) {
            return response()->json(['error' => $this->firstError($e)], 422);
        }

        $product->update([
            'title' => $data['Title'],
            'description' => DescriptionSanitizer::sanitize($data['Description'] ?? null),
            'price' => $data['Price'],
            'is_active' => $this->toBoolean($data['Active']),
        ]);

        AuditLog::record('Product Updated via Sheet', "\"{$product->title}\" (#{$product->id})");

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updateVariant(int $id, array $values): JsonResponse
    {
        $variant = ProductVariant::find($id);

        if (! $variant) {
            return response()->json(['error' => "No variant with ID {$id}. Add new variants from the app, not by adding a blank row."], 404);
        }

        try {
            $data = validator($values, [
                'Cost Price' => ['required', 'numeric', 'min:0'],
                'Price' => ['nullable', 'numeric', 'min:0'],
                'Stock Status' => ['required', 'in:in_stock,low_stock,out_of_stock'],
                'Active' => ['required'],
            ])->validate();
        } catch (ValidationException $e) {
            return response()->json(['error' => $this->firstError($e)], 422);
        }

        $variant->update([
            'cost_price' => $data['Cost Price'],
            'price' => $data['Price'] ?? null,
            'stock_status' => $data['Stock Status'],
            'is_active' => $this->toBoolean($data['Active']),
        ]);

        AuditLog::record('Variant Updated via Sheet', "\"{$variant->option_label}\" (#{$variant->id})");

        return response()->json(['status' => 'ok']);
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(is_string($value) ? strtoupper(trim($value)) : $value, ['TRUE', 'YES', '1', 1], true);
    }

    private function firstError(ValidationException $e): string
    {
        return (string) collect($e->errors())->flatten()->first();
    }
}
