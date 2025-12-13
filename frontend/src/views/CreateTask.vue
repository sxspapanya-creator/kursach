<template>
  <div class="create-task">
    <div class="header">
      <h1>Create New Task</h1>
      <router-link to="/tasks" class="btn btn-secondary">Back to Tasks</router-link>
    </div>

    <form @submit.prevent="submitTask" class="task-form">
      <div class="form-group">
        <label for="title">Title *</label>
        <input
            type="text"
            id="title"
            v-model="form.title"
            required
            placeholder="Enter task title"
        >
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea
            id="description"
            v-model="form.description"
            rows="4"
            placeholder="Enter task description"
        ></textarea>
      </div>

      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" v-model="form.status">
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" :disabled="loading" class="btn btn-primary">
          {{ loading ? 'Creating...' : 'Create Task' }}
        </button>
        <router-link to="/tasks" class="btn btn-secondary">Cancel</router-link>
      </div>

      <div v-if="error" class="error-message">
        {{ error }}
      </div>

      <div v-if="success" class="success-message">
        Task created successfully!
      </div>
    </form>
  </div>
</template>

<script>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'CreateTask',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const error = ref('')
    const success = ref(false)

    const form = reactive({
      title: '',
      description: '',
      status: 'pending'
    })

    const submitTask = async () => {
      loading.value = true
      error.value = ''
      success.value = false

      try {
        await axios.post('/api/tasks', form)
        success.value = true
        form.title = ''
        form.description = ''
        form.status = 'pending'

        // Redirect to task list after 2 seconds
        setTimeout(() => {
          router.push('/tasks')
        }, 2000)
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to create task'
      } finally {
        loading.value = false
      }
    }

    return {
      form,
      loading,
      error,
      success,
      submitTask
    }
  }
}
</script>

<style scoped>
.task-form {
  max-width: 600px;
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #bdc3c7;
  border-radius: 4px;
  font-size: 1rem;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #3498db;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.error-message {
  background-color: #e74c3c;
  color: white;
  padding: 1rem;
  border-radius: 4px;
  margin-top: 1rem;
}

.success-message {
  background-color: #27ae60;
  color: white;
  padding: 1rem;
  border-radius: 4px;
  margin-top: 1rem;
}
</style>