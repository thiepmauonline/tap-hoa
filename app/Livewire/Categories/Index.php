<?php

namespace App\Livewire\Categories;

use Livewire\Component;
use App\Models\Category;

class Index extends Component
{
    public string $name = '';
    public string $description = '';
    public ?int $categoryId = null;
    public bool $isModalOpen = false;

    public function openModal()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->categoryId = null;
        $this->name = '';
        $this->description = '';
    }

    public function edit(int $id)
    {
        $cat = Category::where('store_id', auth()->user()->store_id)->findOrFail($id);
        $this->categoryId = $cat->id;
        $this->name = $cat->name;
        $this->description = $cat->description ?? '';
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->validate(['name' => 'required|string|max:255']);
        $storeId = auth()->user()->store_id;

        if ($this->categoryId) {
            Category::where('store_id', $storeId)->where('id', $this->categoryId)->update([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            session()->flash('success', 'Đã cập nhật danh mục!');
        } else {
            Category::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'description' => $this->description,
                'status' => 1,
            ]);
            session()->flash('success', 'Đã thêm danh mục mới!');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        $category = Category::where('store_id', auth()->user()->store_id)->findOrFail($id);
        if ($category->products()->exists()) {
            session()->flash('error', 'Không thể xóa danh mục đang có sản phẩm.');
            return;
        }
        $category->delete();
        session()->flash('success', 'Đã xóa danh mục!');
    }

    public function render()
    {
        $categories = Category::where('store_id', auth()->user()->store_id)
            ->withCount('products')
            ->get();

        return view('livewire.categories.index', [
            'categories' => $categories,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Danh mục Sản phẩm']);
    }
}
