<template>
  <div class="task-list">
    <div class="header">
      <h1>Task List</h1>
      <router-link to="/tasks/create" class="btn btn-primary">Add New Task</router-link>
    </div>

    <div v-if="loading" class="loading">Loading tasks...</div>

    <div v-else-if="tasks.length === 0" class="empty-state">
      <p>No tasks found.</p>
      <router-link to="/tasks/create" class="btn btn-primary">Create your first task</router-link>
    </div>

    <div v-else class="tasks-grid">
      <div v-for="task in tasks" :key="task.id" class="task-card">
        <h3>{{ task.title }}</h3>
        <p>{{ task.description }}</p>
        <div class="task-meta">
          <span :class="['status', task.status]">{{ task.status }}</span>
          <span class="date">Created: {{ formatDate(task.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'TaskList',
  setup() {
    const tasks = ref([])
    const loading = ref(true)

    const fetchTasks = async () => {
      try {
        const response = await axios.get('/api/tasks')
        tasks.value = response.data
      } catch (error) {
        console.error('Error fetching tasks:', error)
      } finally {
        loading.value = false
      }
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString()
    }

    onMounted(() => {
      fetchTasks()
    })

    return {
      tasks,
      loading,
      formatDate
    }
  }
}
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.tasks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.task-card {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  border-left: 4px solid #3498db;
}

.task-card h3 {
  color: #2c3e50;
  margin-bottom: 0.5rem;
}

.task-card p {
  color: #7f8c8d;
  margin-bottom: 1rem;
}

.task-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}

.status.pending {
  background-color: #f39c12;
  color: white;
}

.status.completed {
  background-color: #27ae60;
  color: white;
}

.date {
  font-size: 0.8rem;
  color: #95a5a6;
}

.loading, .empty-state {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

.empty-state p {
  margin-bottom: 1rem;
}
</style>