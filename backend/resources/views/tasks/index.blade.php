@extends('layouts.app')

@section('title', 'Список задач')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Список задач</h2>
                <a href="{{ route('tasks.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить задачу
                </a>
            </div>

            <!-- Фильтр по статусу -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('tasks.index') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">Все статусы</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>В ожидании</option>
                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>В процессе</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Завершено</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Список задач -->
            @if($tasks->count() > 0)
                <div class="list-group">
                    @foreach($tasks as $task)
                        <div class="list-group-item task-card mb-2 {{ $task->status == 'completed' ? 'completed' : '' }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">{{ $task->title }}</h5>
                                    <p class="mb-1">{{ $task->description }}</p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i>
                                        @if($task->due_date)
                                            {{ $task->due_date->format('d.m.Y') }}
                                        @else
                                            Дата не указана
                                        @endif
                                        <span class="badge bg-{{
                                        $task->status == 'completed' ? 'success' :
                                        ($task->status == 'in_progress' ? 'warning' : 'secondary')
                                    }} ms-2">
                                        {{ $task->status == 'completed' ? 'Завершено' :
                                          ($task->status == 'in_progress' ? 'В процессе' : 'В ожидании') }}
                                    </span>
                                    </small>
                                    @if($task->attachment)
                                        <br>
                                        <small>
                                            <i class="bi bi-paperclip"></i>
                                            <a href="{{ Storage::url($task->attachment) }}" target="_blank">Прикрепленный файл</a>
                                        </small>
                                    @endif
                                </div>
                                <div class="btn-group">
                                    <a href="{{ route('tasks.edit', $task->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Редактировать
                                    </a>
                                    <form action="{{ route('tasks.destroy', $task->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить задачу?')">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Пагинация -->
                <div class="mt-4">
                    {{ $tasks->links() }}
                </div>
            @else
                <div class="alert alert-info">
                    Задачи не найдены. <a href="{{ route('tasks.create') }}">Создайте первую задачу</a>.
                </div>
            @endif
        </div>
    </div>
@endsection