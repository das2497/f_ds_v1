<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subcategory;
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
                'Category' => ['nullable', 'string', 'max:255'],
                'Subcategory' => ['nullable', 'string', 'max:255'],
            ])->validate();

            [$categoryId, $subcategoryId] = $this->resolveCategory($data['Category'] ?? null, $data['Subcategory'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['error' => $this->firstError($e)], 422);
        }

        $product->update([
            'title' => $data['Title'],
            'description' => DescriptionSanitizer::sanitize($data['Description'] ?? null),
            'price' => $data['Price'],
            'is_active' => $this->toBoolean($data['Active']),
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
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
                'Stock Status' => ['required', 'in:in_stock,out_of_stock'],
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

    /**
     * The Sheet shows/edits categories by name (an admin recognizes
     * "Toys & Games", not category_id 7), so a hand-edit has to be
     * resolved back to IDs here — matched case-insensitively, rejected
     * with a clear message (shown back in the Sheet's Sync Status column)
     * on no match, rather than silently leaving the category unchanged
     * or guessing at a close name.
     *
     * @return array{0: ?int, 1: ?int} [category_id, subcategory_id]
     */
    private function resolveCategory(?string $categoryName, ?string $subcategoryName): array
    {
        if (blank($categoryName)) {
            if (filled($subcategoryName)) {
                throw ValidationException::withMessages(['Category' => 'Set a Category before setting a Subcategory.']);
            }

            return [null, null];
        }

        $category = Category::whereRaw('LOWER(name) = ?', [strtolower(trim($categoryName))])->first();

        if (! $category) {
            throw ValidationException::withMessages(['Category' => "No category named \"{$categoryName}\" — check the spelling."]);
        }

        if (blank($subcategoryName)) {
            return [$category->id, null];
        }

        $subcategory = Subcategory::where('category_id', $category->id)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($subcategoryName))])
            ->first();

        if (! $subcategory) {
            throw ValidationException::withMessages(['Subcategory' => "No subcategory named \"{$subcategoryName}\" under \"{$category->name}\" — check the spelling."]);
        }

        return [$category->id, $subcategory->id];
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
