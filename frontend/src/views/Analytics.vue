<template>
  <div class="analytics-page">
    <!-- Заголовок и управление периодом -->
    <div class="header">
      <h1>📊 Аналитика бюджета</h1>
      <div class="period-controls">
        <div class="period-tabs">
          <button
              :class="['period-tab', { active: selectedPeriod === 'month' }]"
              @click="selectedPeriod = 'month'"
          >
            📅 Месяц
          </button>
          <button
              :class="['period-tab', { active: selectedPeriod === 'year' }]"
              @click="selectedPeriod = 'year'"
          >
            📆 Год
          </button>
        </div>
        <input
            v-if="selectedPeriod === 'month'"
            v-model="selectedDate"
            type="month"
            @change="fetchAnalytics"
            class="date-input"
        >
        <input
            v-else
            v-model="selectedYear"
            type="number"
            min="2020"
            :max="new Date().getFullYear()"
            @change="fetchAnalytics"
            class="year-input"
        >
        <button @click="fetchAnalytics" :disabled="loading" class="refresh-btn">
          {{ loading ? 'Обновление...' : '🔄 Обновить' }}
        </button>
      </div>
    </div>

    <!-- Модальное окно для результатов -->
    <div v-if="showResultModal" class="modal-overlay" @click="showResultModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3 class="modal-title">
            <span class="modal-icon">{{ resultModal.icon }}</span>
            {{ resultModal.title }}
          </h3>
          <button @click="showResultModal = false" class="modal-close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <pre class="result-json">{{ resultModal.data }}</pre>
        </div>
        <div class="modal-footer">
          <button @click="showResultModal = false" class="btn btn-secondary">Закрыть</button>
        </div>
      </div>
    </div>

    <!-- Основные метрики -->
    <div class="metrics-grid">
      <div class="metric-card health-card" :style="{ borderColor: financialHealth.color }">
        <div class="metric-tooltip">
          <div class="metric-icon">❤️</div>
          <div class="metric-content">
            <h3>Финансовое здоровье</h3>
            <div class="metric-value">{{ financialHealth.score }}/100</div>
            <div class="metric-label">{{ financialHealth.status_label }}</div>
            <div class="health-progress">
              <div class="progress-bar" :style="{ width: financialHealth.score + '%', backgroundColor: financialHealth.color }"></div>
            </div>
          </div>
          <div class="tooltip-text">
            <strong>Как рассчитано?</strong><br>
            📊 Ликвидность (30%): {{ financialHealth.components?.liquidity?.score || 0 }}%<br>
            <span class="tooltip-sub">
              Остаток до зарплаты: {{ formatMoney(financialHealth.components?.liquidity?.balance) }}<br>
              Дней до зарплаты: {{ financialHealth.components?.liquidity?.days_until_salary || 0 }}
            </span><br>
            🛡️ Подушка (30%): {{ financialHealth.components?.emergency_fund?.score || 0 }}%<br>
            <span class="tooltip-sub">
              Сбережения: {{ formatMoney(financialHealth.components?.emergency_fund?.savings) }}<br>
              Хватит на: {{ financialHealth.components?.emergency_fund?.months_coverage || 0 }} мес.
            </span><br>
            💳 Долги (20%): {{ financialHealth.components?.debt_load?.score || 0 }}%<br>
            <span class="tooltip-sub">
              Платежи: {{ formatMoney(financialHealth.components?.debt_load?.monthly_payments) }}
            </span><br>
            💰 Норма сбережений (20%): {{ financialHealth.components?.savings_rate?.score || 0 }}%<br>
            <span class="tooltip-sub">
              Сэкономлено: {{ formatMoney(financialHealth.components?.savings_rate?.saved_amount) }}
            </span><br>
            <span class="tooltip-score">⭐ Итог: {{ financialHealth.score }}/100</span>
          </div>
        </div>
      </div>

      <div class="metric-card income-card">
        <div class="metric-icon">📈</div>
        <div class="metric-content">
          <h3>Доходы</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.income) }}</div>
          <div class="metric-label">{{ periodLabel }}</div>
        </div>
      </div>

      <div class="metric-card expense-card">
        <div class="metric-icon">📉</div>
        <div class="metric-content">
          <h3>Расходы</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.expenses) }}</div>
          <div class="metric-label">{{ periodLabel }}</div>
        </div>
      </div>

      <div class="metric-card balance-card" :class="balanceClass">
        <div class="metric-icon">⚖️</div>
        <div class="metric-content">
          <h3>Баланс</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.balance) }}</div>
          <div class="metric-label">чистый остаток</div>
        </div>
      </div>

      <div class="metric-card savings-card">
        <div class="metric-icon">💰</div>
        <div class="metric-content">
          <h3>Норма сбережений</h3>
          <div class="metric-value">{{ processedTotals.savings_rate?.toFixed(1) || '0' }}%</div>
          <div class="metric-label">от дохода</div>
          <div class="savings-progress">
            <div class="progress-bar" :style="{ width: Math.min(processedTotals.savings_rate || 0, 100) + '%' }"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Аномалии -->
    <div class="anomalies-section">
      <div class="section-header">
        <h2>⚠️ Выявленные аномалии</h2>
        <span class="anomalies-count">Найдено: {{ detectedAnomalies.length }}</span>
      </div>
      <div class="anomalies-table-container">
        <table class="anomalies-table">
          <thead>
          <tr>
            <th style="width: 40px">✓</th>
            <th>Описание</th>
            <th>Дата</th>
            <th>Категория</th>
            <th>Способ оплаты</th>
            <th>Сумма</th>
            <th>Курс</th>
            <th>Сумма (BYN)</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="anomaly in detectedAnomalies" :key="anomaly.id" :class="{ 'anomaly-changed': hasLocalChange(anomaly) }">
            <td class="checkbox-cell">
              <input type="checkbox" :checked="getLocalAnomalyStatus(anomaly)" @change="toggleLocalAnomalyStatus(anomaly)">
            </td>
            <td class="description-cell">{{ anomaly.description || '—' }}</td>
            <td class="date-cell">{{ formatDate(anomaly.date) }}</td>
            <td class="category-cell">
            <span class="category-badge" :style="{ backgroundColor: anomaly.category_color + '20', color: anomaly.category_color }">
              {{ anomaly.category_name || 'Без категории' }}
            </span>
            </td>
            <td class="payment-cell">
            <span class="payment-method-badge" :class="anomaly.payment_method">
              {{ getPaymentMethodText(anomaly.payment_method) }}
            </span>
            </td>
            <td class="amount-cell">
            <span class="original-amount">
              {{ formatMoneyAmount(anomaly.original_amount || anomaly.amount) }}
              {{ anomaly.currency_code || 'BYN' }}
            </span>
            </td>
            <td class="rate-cell">
              <span v-if="anomaly.exchange_rate && anomaly.exchange_rate !== 1">{{ formatExchangeRate(anomaly.exchange_rate) }}</span>
              <span v-else class="rate-na">—</span>
            </td>
            <td class="byn-cell">
              <strong>{{ formatMoney(anomaly.amount_in_byn || anomaly.amount) }}</strong>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <div class="anomalies-footer">
        <div class="anomalies-total">
          <span>Общая сумма аномалий (BYN):</span>
          <strong>{{ formatMoney(localAnomaliesTotalAmount) }}</strong>
        </div>
        <div class="anomalies-actions">
          <div class="anomalies-hint">
            💡 Отметьте чекбокс, если данная транзакция является аномалией и её не стоит учитывать для планирования
            <span class="hint-note">(не влияет на текущий месячный бюджет)</span>
          </div>
          <div class="action-buttons">
            <button v-if="hasLocalChanges" @click="applyAnomalyChanges" :disabled="applyingChanges" class="btn-apply">
              {{ applyingChanges ? 'Применение...' : '✅ Применить изменения' }}
            </button>
            <button v-if="hasLocalChanges" @click="resetLocalChanges" class="btn-cancel">❌ Отменить</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Расходы по категориям (таблица) -->
    <div class="categories-section">
      <div class="section-header">
        <h2>📂 Расходы по категориям</h2>
        <div class="section-header-right">
          <span class="total-expenses">Всего: {{ formatMoney(processedTotals.expenses) }}</span>
          <span class="period-badge">{{ periodLabel }}</span>
        </div>
      </div>
      <div class="categories-table-container">
        <table class="categories-table">
          <thead>
          <tr><th>Категория</th><th>Сумма</th><th>Доля</th><th>Прогресс</th></tr>
          </thead>
          <tbody>
          <tr v-for="cat in categorySpending" :key="cat.id">
            <td class="category-name-cell"><div class="category-color" :style="{ backgroundColor: cat.color }"></div>{{ cat.name }}</td>
            <td class="category-amount-cell">{{ formatMoney(cat.total) }}</td>
            <td class="category-percent-cell">{{ getCategoryPercent(cat.total) }}%</td>
            <td class="category-progress-cell"><div class="table-progress"><div class="table-progress-bar" :style="{ width: getCategoryPercent(cat.total) + '%', backgroundColor: cat.color }"></div></div></td>
          </tr>
          </tbody>
        </table>
      </div>
      <div v-if="categorySpending.length === 0" class="empty-categories"><div class="empty-icon">📭</div><p>Нет расходов за выбранный период</p></div>
    </div>

    <!-- Планирование расходов -->
    <div v-if="forecastData" class="forecast-section">
      <div class="section-header">
        <h2>📋 Планирование расходов</h2>
      </div>

      <!-- Метрики качества с всплывающими подсказками -->
      <div class="metrics-quality" v-if="forecastData.model_metrics">
        <div class="quality-card">
          <span class="quality-label">MAPE</span>
          <span class="quality-value" :class="getMapeClass(forecastData.model_metrics.mape)">{{ forecastData.model_metrics.mape }}%</span>
          <span class="quality-desc">{{ getMapeDesc(forecastData.model_metrics.mape) }}</span>
          <div class="card-tooltip">
            <strong>📊 Что такое MAPE?</strong><br>
            Средняя абсолютная процентная ошибка<br>
            <span class="tooltip-sub">
              Показывает точность прогноза в процентах.<br>
              Чем меньше значение, тем точнее прогноз.<br><br>
              <strong>Формула:</strong><br>
              MAPE = (1/n) × Σ(|Факт - Прогноз| / Факт) × 100%<br><br>
              <strong>Интерпретация:</strong><br>
              • &lt;10% — отличная точность<br>
              • 10-20% — хорошая точность<br>
              • 20-30% — приемлемая точность<br>
              • &gt;30% — низкая точность
            </span>
          </div>
        </div>

        <div class="quality-card">
          <span class="quality-label">RMSE</span>
          <span class="quality-value">{{ formatMoney(forecastData.model_metrics.rmse) }}</span>
          <span class="quality-desc">средняя ошибка</span>
          <div class="card-tooltip">
            <strong>📐 Что такое RMSE?</strong><br>
            Среднеквадратическая ошибка прогноза<br>
            <span class="tooltip-sub">
              Показывает типичное отклонение прогноза от факта в денежном выражении.<br><br>
              <strong>Формула:</strong><br>
              RMSE = √((1/n) × Σ(Факт - Прогноз)²)<br><br>
              <strong>Интерпретация:</strong><br>
              • Чем меньше RMSE, тем точнее прогноз<br>
              • Показывает ошибку в тех же единицах, что и данные (BYN)<br>
              • Крупные ошибки имеют больший вес из-за возведения в квадрат
            </span>
          </div>
        </div>

        <div class="quality-card">
          <span class="quality-label">Вариация (CV)</span>
          <span class="quality-value" :class="getCvClass(forecastData.cv_percent)">
            <template v-if="forecastData.cv_percent !== null && forecastData.cv_percent !== undefined">
              {{ forecastData.cv_percent }}%
            </template>
            <template v-else>
              —
            </template>
          </span>
          <span class="quality-desc">{{ forecastData.cv_text || 'Нет данных' }}</span>
          <div class="card-tooltip">
            <strong>📊 Что такое коэффициент вариации (CV)?</strong><br>
            <span class="tooltip-sub">
              Показывает, насколько расходы отличаются от месяца к месяцу.<br><br>
              <strong>Формула:</strong><br>
              CV = σ / μ × 100%<br>
              где σ — стандартное отклонение, μ — среднее значение<br><br>
              <strong>Чем ниже CV, тем точнее будет прогноз!</strong>
            </span>
          </div>
        </div>

        <div class="quality-card">
          <span class="quality-label">Метод прогнозирования</span>
          <span class="quality-value">{{ getRussianMethodName(forecastData.model) }}</span>
          <div class="card-tooltip">
            <strong>⚙️ Как работает метод "{{ getRussianMethodName(forecastData.model) }}"?</strong><br>
            <span class="tooltip-sub" v-html="getMethodDescription(forecastData.model)"></span>
          </div>
        </div>
      </div>

      <!-- Три карточки прогноза -->
      <div class="forecast-grid">
        <div class="forecast-card remaining">
          <h3>📅 Остаток {{ currentMonthName }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.remaining_current_month?.forecast_total) }}</div>
          <div class="forecast-detail">Уже потрачено (без учета аномалий): {{ formatMoney(forecastData.remaining_current_month?.already_spent) }}</div>
          <div class="forecast-detail">Средний расход в день: {{ formatMoney(forecastData.remaining_current_month?.weighted_daily_rate) }}</div>
          <div class="forecast-detail">Осталось дней: {{ forecastData.remaining_current_month?.days_left }}</div>
          <div class="forecast-full-month">Весь месяц: {{ formatMoney(forecastData.remaining_current_month?.forecast_full_month) }}</div>
        </div>
        <div class="forecast-card next">
          <h3>📆 {{ forecastData.next_month?.month }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.next_month?.total_forecast) }}</div>
          <div class="forecast-detail">Средний расход: {{ formatMoney(forecastData.next_month?.daily_average) }}/день</div>
          <div class="forecast-detail">Изменение: {{ formatChange(forecastData.next_month?.change_from_previous) }}</div>
          <div class="forecast-trend" :class="forecastData.next_month?.trend">{{ getTrendIcon(forecastData.next_month?.trend) }} {{ getTrendText(forecastData.next_month?.trend) }}</div>
        </div>
        <div class="forecast-card second">
          <h3>📆 {{ forecastData.second_month?.month }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.second_month?.total_forecast) }}</div>
          <div class="forecast-detail">Средний расход: {{ formatMoney(forecastData.second_month?.daily_average) }}/день</div>
          <div class="forecast-detail">Изменение: {{ formatChange(forecastData.second_month?.change_from_previous) }}</div>
          <div class="forecast-trend" :class="forecastData.second_month?.trend">{{ getTrendIcon(forecastData.second_month?.trend) }} {{ getTrendText(forecastData.second_month?.trend) }}</div>
        </div>
      </div>

      <!-- Подневное планирование -->
      <div v-if="forecastData.remaining_current_month?.daily_breakdown?.length" class="daily-forecast">
        <h3>📊 Подневное планирование</h3>
        <div class="daily-scroll-container">
          <div class="daily-chart">
            <div class="chart-bars">
              <div v-for="day in forecastData.remaining_current_month.daily_breakdown" :key="day.date" class="bar-item" :style="{ height: getBarHeight(day.forecast, maxDailyForecast) + 'px' }">
                <div class="bar-tooltip">
                  <span class="tooltip-date">{{ formatDate(day.date) }}</span>
                  <span class="tooltip-day">{{ day.day_of_week }}</span>
                  <span class="tooltip-amount">{{ formatMoney(day.forecast) }}</span>
                </div>
              </div>
            </div>
            <div class="chart-labels">
              <div v-for="day in forecastData.remaining_current_month.daily_breakdown" :key="day.date" class="label-item">
                <span class="label-day">{{ formatDay(day.date) }}</span>
                <span class="label-week">{{ getShortDay(day.day_of_week) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Детальное планирование по категориям - месяц 1 -->
      <div v-if="forecastData.category_forecasts?.length" class="category-forecast">
        <h3>📈 Детальное планирование по категориям на {{ forecastData.next_month?.month || 'следующий месяц' }}</h3>
        <div class="category-forecast-table">
          <div class="table-header"><span>Категория</span><span>Прогноз на месяц</span><span>В день</span><span>Доля бюджета</span></div>
          <div class="table-body">
            <div v-for="cat in forecastData.category_forecasts" :key="cat.category_id" class="table-row">
              <div class="row-category"><div class="cat-dot" :style="{ backgroundColor: cat.color }"></div><span>{{ cat.category_name }}</span></div>
              <div class="row-forecast">{{ formatMoney(cat.forecast) }}</div>
              <div class="row-daily">{{ formatMoney(cat.daily_average) }}</div>
              <div class="row-share"><div class="share-bar"><div class="share-fill" :style="{ width: cat.share_percent + '%', backgroundColor: cat.color }"></div></div><span class="share-value">{{ cat.share_percent }}%</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Детальное планирование по категориям - месяц 2 -->
      <div v-if="forecastData.second_month_category_forecasts?.length" class="category-forecast second-month">
        <h3>📈 Детальное планирование по категориям на {{ forecastData.second_month?.month || 'второй месяц' }}</h3>
        <div class="category-forecast-table">
          <div class="table-header"><span>Категория</span><span>Прогноз на месяц</span><span>В день</span><span>Доля бюджета</span></div>
          <div class="table-body">
            <div v-for="cat in forecastData.second_month_category_forecasts" :key="cat.category_id" class="table-row">
              <div class="row-category"><div class="cat-dot" :style="{ backgroundColor: cat.color }"></div><span>{{ cat.category_name }}</span></div>
              <div class="row-forecast">{{ formatMoney(cat.forecast) }}</div>
              <div class="row-daily">{{ formatMoney(cat.daily_average) }}</div>
              <div class="row-share"><div class="share-bar"><div class="share-fill" :style="{ width: cat.share_percent + '%', backgroundColor: cat.color }"></div></div><span class="share-value">{{ cat.share_percent }}%</span></div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="forecastData.reliability_message" class="reliability-message"><span class="message-icon"></span><span>{{ forecastData.reliability_message }}</span></div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

export default {
  name: 'AnalyticsPage',
  setup() {
    const analytics = ref({ totals: {}, category_spending: [], date_range: {}, financial_health: {}, anomalies_info: {} })
    const forecastData = ref(null)
    const detectedAnomalies = ref([])
    const loading = ref(false)
    const applyingChanges = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const selectedYear = ref(new Date().getFullYear())
    const includeAnomalies = ref(false)
    const showResultModal = ref(false)
    const resultModal = ref({ icon: '', title: '', data: '' })
    const localAnomalyChanges = ref(new Map())

    const formatMoney = (amount) => {
      const num = Number(amount)
      if (isNaN(num)) return '0 Br'
      return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num) + ' Br'
    }

    const formatMoneyAmount = (amount) => {
      const num = Number(amount)
      if (isNaN(num)) return '0'
      return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
    }

    const getRussianMethodName = (method) => {
      const names = {
        'SimpleExtrapolation': 'Линейная экстраполяция',
        'LinearRegression': 'Линейная регрессия',
        'DoubleExponentialSmoothing': 'Двойное сглаживание',
        'HoltWinters': 'Хольта-Уинтерса'
      }
      return names[method] || method
    }

    const formatExchangeRate = (rate) => {
      const num = Number(rate)
      if (isNaN(num)) return '—'
      return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 4, maximumFractionDigits: 4 }).format(num)
    }

    const formatDate = (dateStr) => {
      if (!dateStr) return '—'
      const date = new Date(dateStr)
      return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })
    }

    const formatDay = (dateStr) => {
      const date = new Date(dateStr)
      return date.getDate()
    }

    const formatChange = (percent) => {
      if (!percent || percent === 0) return '0%'
      const sign = percent > 0 ? '+' : ''
      return `${sign}${percent}%`
    }

    const getTrendIcon = (trend) => {
      if (trend === 'growth') return '📈'
      if (trend === 'decline') return '📉'
      return '📊'
    }

    const getTrendText = (trend) => {
      if (trend === 'growth') return 'Рост'
      if (trend === 'decline') return 'Снижение'
      return 'Стабильно'
    }

    const getPaymentMethodText = (method) => {
      const methods = { cash: '💰 Наличные', card: '💳 Карта', transfer: '🏦 Перевод' }
      return methods[method] || method || '—'
    }

    const getMapeClass = (mape) => {
      if (mape < 10) return 'excellent'
      if (mape < 20) return 'good'
      if (mape < 30) return 'normal'
      return 'bad'
    }

    const getMapeDesc = (mape) => {
      if (mape < 10) return 'отличная точность'
      if (mape < 20) return 'хорошая точность'
      if (mape < 30) return 'приемлемая точность'
      return 'низкая точность'
    }

    const getCvClass = (cvPercent) => {
      if (cvPercent === null || cvPercent === undefined) return 'bad'
      if (cvPercent < 15) return 'excellent'
      if (cvPercent < 30) return 'good'
      if (cvPercent < 50) return 'normal'
      return 'bad'
    }

    const getShortDay = (dayOfWeek) => {
      const short = { 'Понедельник': 'Пн', 'Вторник': 'Вт', 'Среда': 'Ср', 'Четверг': 'Чт', 'Пятница': 'Пт', 'Суббота': 'Сб', 'Воскресенье': 'Вс' }
      return short[dayOfWeek] || dayOfWeek?.slice(0, 2)
    }

    const getMethodDescription = (method) => {
      const descriptions = {
        'SimpleExtrapolation': `
    <strong> Линейная экстраполяция</strong><br><br>

    <strong> Как работает?</strong><br>
    1. Берет <strong>первый завершенный месяц</strong> и <strong>последний завершенный месяц</strong> из истории<br>
    2. Считает, на сколько в среднем менялись расходы за месяц:<br>
       <code>Среднее_изменение = (Последний_месяц - Первый_месяц) / (Количество_месяцев - 1) (не учитывая текущий неполный месяц)</code><br>
    3. Ограничивает изменение (не больше +20% и не меньше -15% за месяц)<br>
    4. Продлевает (экстраполирует) этот тренд на будущие месяцы<br><br>

    <strong> Формула прогноза:</strong><br>
    <code>y = a + b × x</code><br>
    где:<br>
    • <strong>y</strong> — прогнозируемые расходы<br>
    • <strong>a</strong> — последний известный месяц<br>
    • <strong>b</strong> — среднее изменение за месяц<br>
    • <strong>x</strong> — шаг прогноза (1, 2, 3...)<br>

    <strong> Важные ограничения:</strong><br>
    • Максимальный рост за месяц: <strong>+20%</strong><br>
    • Максимальное падение за месяц: <strong>-15%</strong><br>
    • Минимальный прогноз: <strong>30% от последнего месяца</strong> (защита от нуля)<br>
    • Не учитывает сезонные колебания (новый год, лето и т.д.)<br>
    • Не чувствителен к резким скачкам в середине периода<br>
    • Работает только для краткосрочного прогноза (1-2 месяца)<br><br>
  `,
        'LinearRegression': `
  <strong> Линейная регрессия </strong><br><br>

  <strong> Как работает?</strong><br>
  1. Строит оптимальную прямую линию тренда через ВСЕ точки данных<br>
  2. Использует <strong>метод наименьших квадратов</strong> — прямая максимально близка ко всем точкам<br>
  3. Сглаживает случайные колебания (шум) в данных<br>
  4. Продлевает (экстраполирует) этот тренд на будущие месяцы<br><br>

  <strong> Формула прогноза (метод наименьших квадратов):</strong><br>
  <code>y = a + b × x (зависимость расходов от времени)</code><br>
  где:<br>
  • <strong>y</strong> — прогнозируемые расходы<br>
  • <strong>a</strong> — точка пересечения с осью Y (intercept)<br>
  • <strong>b</strong> — коэффициент наклона (slope)<br>
  • <strong>x</strong> — порядковый номер месяца (0, 1, 2, 3...)<br><br>

  <strong> Как рассчитываются коэффициенты?</strong><br>
  <code>b = (n × Σxy - Σx × Σy) / (n × Σx² - (Σx)²)</code><br>
  <code>a = (Σy - b × Σx) / n</code><br>
  где:<br>
  • <strong>n</strong> — количество месяцев<br>
  • <strong>Σxy</strong> — сумма произведений x и y<br>
  • <strong>Σy</strong> — сумма расходов<br>

  <strong> Пример работы:</strong><br>
  Данные за 5 месяцев: 1000, 1150, 1180, 1320, 1400 BYN<br><br>

  <strong>Расчет коэффициентов:</strong><br>
  • Σy = 1000+1150+1180+1320+1400 = 6050<br>
  • Σxy = 0×1000 + 1×1150 + 2×1180 + 3×1320 + 4×1400 = 13070<br>
  • n = 5<br><br>

  <code>b = (5×13070 - 10×6050) / (5×30 - 10²) = (65350 - 60500) / (150 - 100) = 4850 / 50 = 97</code><br>
  <code>a = (6050 - 97×10) / 5 = (6050 - 970) / 5 = 5080 / 5 = 1016</code><br><br>

  <strong>Уравнение прямой:</strong> <code>y = 1016 + 97 × x</code><br>
  <strong>Прогноз на следующий месяц (x=5):</strong> 1016 + 97×5 = <strong>1501 BYN</strong><br><br>

  <strong> Важные ограничения:</strong><br>
  • Максимальный рост: <strong>не более чем ×2 (200%) от последнего месяца</strong><br>
  • Максимальное падение: <strong>не менее 30% от последнего месяца</strong><br>
  • Не учитывает сезонные колебания (новый год, лето и т.д.)<br>
`,
        'DoubleExponentialSmoothing': `
  <strong> Двойное экспоненциальное сглаживание или Метод Хольта)</strong><br><br>

  <strong> Как работает?</strong><br>
  1. Отслеживает два компонента: <strong>уровень</strong> (сглаженное значение) и <strong>тренд</strong> (скорость изменения)<br>
  2. Каждый месяц обновляет оба компонента с учетом новых данных<br>
  3. Чем свежее данные, тем больше их влияние (экспоненциальное затухание)<br>
  4. Прогноз = текущий уровень + тренд × количество шагов<br>
  5. Параметры α (альфа) и β (бета) подбираются автоматически<br><br>

  <strong>📐 Формулы (метод Хольта):</strong><br>
  <code>Уровень: Lₜ = α × Yₜ + (1-α) × (Lₜ₋₁ + Tₜ₋₁)</code><br>
  <code>Тренд: Tₜ = β × (Lₜ - Lₜ₋₁) + (1-β) × Tₜ₋₁</code><br>
  <code>Прогноз: Fₜ₊ₖ = Lₜ + k × Tₜ</code><br><br>

  где:<br>
  • <strong>Lₜ</strong> — сглаженный уровень в месяце t<br>
  • <strong>Tₜ</strong> — тренд (насколько меняются расходы за месяц)<br>
  • <strong>Yₜ</strong> — фактические расходы в месяце t<br>
  • <strong>α</strong> — коэффициент сглаживания уровня (0.1-0.8)<br>
  • <strong>β</strong> — коэффициент сглаживания тренда (0.05-0.5)<br>
  • <strong>k</strong> — шаг прогноза (1, 2, 3...)<br><br>

  <strong> Как подбираются α и β?</strong><br>
  Алгоритм перебирает разные комбинации и выбирает те, которые дают наименьшую ошибку:<br>

  <strong> Пример работы:</strong><br>
  Расходы за 4 месяца: 1000, 1100, 1300, 1600 BYN<br>
  Подобранные параметры: α = 0.5, β = 0.3<br><br>

  <strong> Начальные значения (через линейную регрессию):</strong><br>
  L₀ = 850, T₀ = 150<br><br>

  <strong> Пошаговое сглаживание:</strong><br>
  • Месяц 1 (факт 1100): L₁ = 0.5×1100 + 0.5×(850+150) = 1050, T₁ = 165<br>
  • Месяц 2 (факт 1300): L₂ = 0.5×1300 + 0.5×(1050+165) = 1257.5, T₂ = 177.75<br>
  • Месяц 3 (факт 1600): L₃ = 0.5×1600 + 0.5×(1257.5+177.75) = 1517.6, T₃ = 202.4<br><br>

  <strong> Прогноз на следующий месяц:</strong><br>
  F = 1517.6 + 1 × 202.4 = <strong>1720 BYN</strong><br><br>

  <strong>Важные ограничения:</strong><br>
  • Максимальный рост: <strong>не более чем ×2 (200%) от последнего месяца</strong><br>
  • Максимальное падение: <strong>не менее 30% от последнего месяца</strong><br>
  • Не учитывает сезонные колебания (новый год, лето и т.д.)<br>

  <strong> Преимущества перед LinearRegression:</strong><br>
  • Адаптируется к изменению тренда (может ускоряться или замедляться)<br>
  • Более свежие данные имеют больший вес<br>
  • Лучше работает при нелинейных изменениях<br>
  • Дает более точный прогноз при волатильных данных<br><br>
`,
        'HoltWinters': `
  <strong> Тройное экспоненциальное сглаживание</strong><br><br>

  <strong> Как работает?</strong><br>
  1. Отслеживает <strong>три компонента</strong>: уровень, тренд и сезонность<br>
  2. Сезонный период = <strong>12 месяцев</strong> (годовая сезонность)<br>
  3. Каждый месяц обновляет все три компонента с учетом новых данных<br>
  4. Чем свежее данные, тем больше их влияние<br>
  5. Прогноз = (уровень + тренд × шаг) × сезонный коэффициент<br>
  6. Параметры α, β, γ подбираются автоматически<br><br>

  <strong> Формулы (метод Хольта-Уинтерса):</strong><br>
  <code>Уровень: Lₜ = α × (Yₜ / Sₜ₋ₚ) + (1-α) × (Lₜ₋₁ + Tₜ₋₁)</code><br>
  <code>Тренд: Tₜ = β × (Lₜ - Lₜ₋₁) + (1-β) × Tₜ₋₁</code><br>
  <code>Сезонность: Sₜ = γ × (Yₜ / Lₜ) + (1-γ) × Sₜ₋ₚ</code><br>
  <code>Прогноз: Fₜ₊ₖ = (Lₜ + k × Tₜ) × Sₜ₊ₖ₋ₚ</code><br><br>

  где:<br>
  • <strong>Lₜ</strong> — сглаженный уровень в месяце t<br>
  • <strong>Tₜ</strong> — тренд (насколько меняются расходы за месяц)<br>
  • <strong>Sₜ</strong> — сезонный коэффициент (например, декабрь = 1.5, июль = 0.7)<br>
  • <strong>Yₜ</strong> — фактические расходы в месяце t<br>
  • <strong>α</strong> (альфа) — коэффициент сглаживания уровня (0.1-0.9)<br>
  • <strong>β</strong> (бета) — коэффициент сглаживания тренда (0.05-0.5)<br>
  • <strong>γ</strong> (гамма) — коэффициент сглаживания сезонности (0.1-0.9)<br>
  • <strong>p</strong> — сезонный период (12 месяцев)<br>
  • <strong>k</strong> — шаг прогноза (1, 2, 3...)<br><br>

  <strong> Как подбираются α, β и γ?</strong><br>
  Алгоритм перебирает разные комбинации и выбирает те, которые дают наименьшую ошибку MAPE:<br>

  <strong> Пример работы:</strong><br>
  Данные за 24 месяца с явной сезонностью:<br><br>

  <strong> Вычисление сезонных коэффициентов:</strong><br>

  <strong>Базовый уровень и тренд:</strong><br>
  • Уровень (L) = 1000 BYN<br>
  • Тренд (T) = +10 BYN в месяц (расходы растут)<br><br>

  <strong>Прогноз на декабрь следующего года:</strong><br>
  Без сезонности: 1000 + 10×12 = 1120 BYN<br>
  С учетом сезонности: <strong>1120 × 1.5 (пример) = 1680 BYN</strong><br><br>

  <strong>Прогноз на июль следующего года:</strong><br>
  Без сезонности: 1000 + 10×6 = 1060 BYN<br>
  С учетом сезонности: <strong>1060 × 1.15 ≈ 1219 BYN</strong><br><br>
`
      }
      return descriptions[method] || `
        <strong>Метод прогнозирования</strong><br>
        <span class="tooltip-sub">
          Алгоритм автоматически выбирает оптимальную стратегию на основе:<br><br>
          • Количества месяцев данных<br>
          • Стабильности расходов (коэффициент вариации)<br>
          • Наличия тренда или сезонности<br><br>
          <strong>Доступные методы:</strong><br>
          • SimpleExtrapolation (3-6 месяцев)<br>
          • LinearRegression (7-14 месяцев)<br>
          • DoubleExponentialSmoothing (15-23 месяца)<br>
          • HoltWinters (24+ месяцев)
        </span>
      `
    }

    const getLocalAnomalyStatus = (anomaly) => {
      if (localAnomalyChanges.value.has(anomaly.id)) return localAnomalyChanges.value.get(anomaly.id)
      return anomaly.is_anomaly || false
    }

    const hasLocalChange = (anomaly) => localAnomalyChanges.value.has(anomaly.id)
    const toggleLocalAnomalyStatus = (anomaly) => {
      const currentStatus = getLocalAnomalyStatus(anomaly)
      localAnomalyChanges.value.set(anomaly.id, !currentStatus)
    }
    const hasLocalChanges = computed(() => localAnomalyChanges.value.size > 0)
    const localAnomaliesTotalAmount = computed(() => detectedAnomalies.value.filter(a => getLocalAnomalyStatus(a)).reduce((sum, a) => sum + (a.amount_in_byn || a.amount || 0), 0))
    const resetLocalChanges = () => localAnomalyChanges.value.clear()

    const applyAnomalyChanges = async () => {
      if (localAnomalyChanges.value.size === 0) return
      applyingChanges.value = true
      try {
        const changes = []
        for (const [id, newStatus] of localAnomalyChanges.value.entries()) {
          const anomaly = detectedAnomalies.value.find(a => a.id === parseInt(id))
          if (anomaly && anomaly.is_anomaly !== newStatus) changes.push({ id: parseInt(id), is_anomaly: newStatus })
        }
        if (changes.length === 0) { resetLocalChanges(); return }
        await axios.post('/api/transactions/mark-anomalies', { anomalies: changes }, { credentials: 'include' })
        for (const change of changes) {
          const anomaly = detectedAnomalies.value.find(a => a.id === change.id)
          if (anomaly) anomaly.is_anomaly = change.is_anomaly
        }
        resetLocalChanges()
        await fetchAnalytics()
      } catch (err) { console.error('Failed to update anomalies:', err); alert('Не удалось сохранить изменения') }
      finally { applyingChanges.value = false }
    }

    const processedTotals = computed(() => {
      const totals = analytics.value.totals || {}
      return { income: totals.income || 0, expenses: totals.expenses || 0, balance: totals.balance || 0, savings_rate: totals.savings_rate || 0 }
    })
    const categorySpending = computed(() => analytics.value.category_spending || [])
    const financialHealth = computed(() => analytics.value.financial_health || { score: 0, status: 'poor', status_label: 'Не определено', color: '#95a5a6', components: {} })
    const balanceClass = computed(() => { const b = processedTotals.value.balance; if (b > 0) return 'positive'; if (b < 0) return 'negative'; return 'neutral' })
    const currentMonthName = computed(() => new Date().toLocaleString('ru', { month: 'long', year: 'numeric' }))
    const periodLabel = computed(() => {
      if (selectedPeriod.value === 'month') return new Date(selectedDate.value).toLocaleString('ru', { month: 'long', year: 'numeric' })
      return `${selectedYear.value} год`
    })
    const totalExpensesSum = computed(() => categorySpending.value.reduce((sum, cat) => sum + (cat.total || 0), 0))
    const maxDailyForecast = computed(() => {
      if (!forecastData.value?.remaining_current_month?.daily_breakdown?.length) return 0
      return Math.max(...forecastData.value.remaining_current_month.daily_breakdown.map(d => d.forecast), 1)
    })

    const getBarHeight = (forecast, maxForecast) => {
      if (maxForecast === 0) return 0
      const minHeight = 50, maxHeight = 160
      return minHeight + (maxHeight - minHeight) * (forecast / maxForecast)
    }

    const getCategoryPercent = (amount) => {
      if (totalExpensesSum.value === 0) return 0
      return Math.round((amount / totalExpensesSum.value) * 100)
    }

    const fetchAnalytics = async () => {
      try {
        loading.value = true
        let year, month
        if (selectedPeriod.value === 'month') [year, month] = selectedDate.value.split('-')
        else year = selectedYear.value
        const overviewRes = await axios.get('/api/analytics/overview', { params: { period: selectedPeriod.value, ...(month && { month: parseInt(month) }), year: parseInt(year), include_anomalies: includeAnomalies.value }, credentials: 'include' })
        if (overviewRes.data.status === 'success') analytics.value = overviewRes.data.data || {}
        const forecastRes = await axios.get('/api/forecast', { credentials: 'include' })
        if (forecastRes.data.status === 'success') {
          forecastData.value = forecastRes.data.data
          detectedAnomalies.value = forecastRes.data.data.anomalies_list || []
        }
        resetLocalChanges()
      } catch (err) {
        console.error('Analytics fetch error:', err)
        if (err.response?.data?.message) { resultModal.value = { icon: '❌', title: 'Ошибка', data: err.response.data.message }; showResultModal.value = true }
      } finally { loading.value = false }
    }

    onMounted(() => fetchAnalytics())
    watch([selectedPeriod, selectedDate, selectedYear, includeAnomalies], () => { if (!loading.value) fetchAnalytics() })

    return {
      analytics, getRussianMethodName, forecastData, detectedAnomalies, loading, applyingChanges, selectedPeriod, selectedDate, selectedYear,
      includeAnomalies, showResultModal, resultModal, processedTotals, categorySpending, financialHealth, balanceClass,
      currentMonthName, periodLabel, localAnomaliesTotalAmount, hasLocalChanges, maxDailyForecast, formatMoney,
      formatMoneyAmount, formatExchangeRate, formatDate, formatDay, formatChange, getTrendIcon, getTrendText,
      getPaymentMethodText, getMapeClass, getMapeDesc, getCvClass, getShortDay, getMethodDescription, getBarHeight, getCategoryPercent,
      getLocalAnomalyStatus, hasLocalChange, toggleLocalAnomalyStatus, resetLocalChanges, applyAnomalyChanges, fetchAnalytics
    }
  }
}
</script>

<style scoped>
@import '../css/analytics.css';
</style>