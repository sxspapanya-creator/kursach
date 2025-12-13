<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query();

        // Фильтрация по статусу
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Сортировка по дате создания
        $query->orderBy('created_at', 'desc');

        $tasks = $query->paginate(10);
        $statuses = ['pending', 'in_progress', 'completed'];

        return view('tasks.index', compact('tasks', 'statuses'));
    }

    public function create()
    {
        return view('tasks.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'status' => 'required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date',
            'attachment' => 'nullable|file|max:2048'
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('uploads', 'public');
            $validated['attachment'] = $path;
        }

        Task::create($validated);

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно создана.');
    }

    public function edit(Task $task)
    {
        return view('tasks.edit', compact('task'));
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'status' => 'required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date',
            'attachment' => 'nullable|file|max:2048'
        ]);

        if ($request->hasFile('attachment')) {
            // Удаляем старый файл, если есть
            if ($task->attachment) {
                Storage::disk('public')->delete($task->attachment);
            }

            $path = $request->file('attachment')->store('uploads', 'public');
            $validated['attachment'] = $path;
        }

        $task->update($validated);

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно обновлена.');
    }

    public function destroy(Task $task)
    {
        // Удаляем прикрепленный файл, если есть
        if ($task->attachment) {
            Storage::disk('public')->delete($task->attachment);
        }

        $task->delete();

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно удалена.');
    }
}