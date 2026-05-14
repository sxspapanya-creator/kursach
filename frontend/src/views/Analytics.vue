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
    <div v-if="detectedAnomalies.length > 0" class="anomalies-section">
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
            <td>{{ formatDate(anomaly.date) }}</td>
            <td>
                <span class="category-badge" :style="{ backgroundColor: anomaly.category_color + '20', color: anomaly.category_color }">
                  {{ anomaly.category_name || 'Без категории' }}
                </span>
            </td>
            <td>
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
            <td class="byn-cell"><strong>{{ formatMoney(anomaly.amount_in_byn || anomaly.amount) }}</strong></td>
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
          <span class="quality-label">Надежность</span>
          <span class="quality-value" :class="forecastData.confidence_level">{{ forecastData.confidence }}%</span>
          <span class="quality-desc">{{ forecastData.confidence_text }}</span>
          <div class="card-tooltip">
            <strong>🔮 Как рассчитывается надежность?</strong><br>
            <span class="tooltip-sub">
              Надежность прогноза зависит от двух факторов:<br><br>
              <strong>1. Количество данных (50%):</strong><br>
              • Чем больше месяцев истории, тем выше надежность<br>
              • Максимум 100% при &gt;12 месяцах<br><br>
              <strong>2. Стабильность расходов (50%):</strong><br>
              • Насколько расходы отличаются от месяца к месяцу<br>
              • Коэффициент вариации (CV) — чем он ниже, тем стабильнее<br><br>
              <strong>Итоговая формула:</strong><br>
              Надежность = 0.5 × Score(месяцы) + 0.5 × Score(стабильность)<br><br>
              <strong>Интерпретация:</strong><br>
              • ≥70% — высокая надежность<br>
              • 45-69% — средняя надежность<br>
              • &lt;45% — низкая надежность
            </span>
          </div>
        </div>

        <div class="quality-card">
          <span class="quality-label">Метод</span>
          <span class="quality-value">{{ forecastData.model }}</span>
          <span class="quality-desc">прогнозирования</span>
          <div class="card-tooltip">
            <strong>⚙️ Как работает метод "{{ forecastData.model }}"?</strong><br>
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

      <div v-if="forecastData.reliability_message" class="reliability-message"><span class="message-icon">⚠️</span><span>{{ forecastData.reliability_message }}</span></div>
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

    const getShortDay = (dayOfWeek) => {
      const short = { 'Понедельник': 'Пн', 'Вторник': 'Вт', 'Среда': 'Ср', 'Четверг': 'Чт', 'Пятница': 'Пт', 'Суббота': 'Сб', 'Воскресенье': 'Вс' }
      return short[dayOfWeek] || dayOfWeek?.slice(0, 2)
    }

    const getMethodDescription = (method) => {
      const descriptions = {
        'SimpleExtrapolation': `
          <strong>Для минимальных данных (3-6 месяцев)</strong><br><br>
          <strong>Применяется когда:</strong> мало истории (3-6 месяцев)<br><br>
          <strong>Как работает:</strong><br>
          1. Берет последний известный расход<br>
          2. Рассчитывает среднее изменение за весь период<br>
          3. Ограничивает изменение: максимум +20%, минимум -15%<br>
          4. Экстраполирует тренд на будущие периоды<br><br>
          <strong>Формула:</strong><br>
          Прогноз = Последнее_значение + Среднее_изменение × Шаг<br><br>
          <strong>Ограничения:</strong><br>
          • Не учитывает сезонность<br>
          • Не чувствителен к резким скачкам<br>
          • Работает только для коротких периодов
        `,
        'LinearRegression': `
          <strong>Для стабильных данных (7-14 месяцев)</strong><br><br>
          <strong>Применяется когда:</strong> 7-14 месяцев данных, есть линейный тренд<br><br>
          <strong>Как работает:</strong><br>
          1. Строит линию регрессии через все точки данных<br>
          2. Находит оптимальную прямую (метод наименьших квадратов)<br>
          3. Экстраполирует линию на будущие периоды<br><br>
          <strong>Формула:</strong><br>
          y = a + b × x, где:<br>
          • b = (n×Σxy - Σx×Σy) / (n×Σx² - (Σx)²) — коэффициент наклона<br>
          • a = (Σy - b×Σx) / n — точка пересечения<br><br>
          <strong>Ограничения:</strong><br>
          • Предполагает линейный тренд<br>
          • Чувствителен к выбросам<br>
          • Не учитывает сезонность
        `,
        'DoubleExponentialSmoothing': `
          <strong>Для трендовых данных (15-23 месяца)</strong><br><br>
          <strong>Применяется когда:</strong> 15-23 месяцев данных, есть тренд, но нет сезонности<br><br>
          <strong>Как работает:</strong><br>
          1. Двойное экспоненциальное сглаживание (метод Хольта)<br>
          2. Отслеживает уровень и тренд одновременно<br>
          3. Параметры α (альфа) и β (бета) оптимизируются автоматически<br><br>
          <strong>Формула:</strong><br>
          • Уровень: Lₜ = α×Yₜ + (1-α)×(Lₜ₋₁ + Tₜ₋₁)<br>
          • Тренд: Tₜ = β×(Lₜ - Lₜ₋₁) + (1-β)×Tₜ₋₁<br>
          • Прогноз: Fₜ₊ₖ = Lₜ + k×Tₜ<br><br>
          <strong>Преимущества:</strong><br>
          • Адаптируется к изменениям тренда<br>
          • Более свежие данные имеют больший вес<br>
          • Не требует сезонности
        `,
        'HoltWinters': `
          <strong>Для сезонных данных (24+ месяца)</strong><br><br>
          <strong>Применяется когда:</strong> 24+ месяцев данных, есть четкая сезонность (например, годовая)<br><br>
          <strong>Как работает:</strong><br>
          1. Тройное экспоненциальное сглаживание<br>
          2. Учитывает уровень, тренд и сезонность<br>
          3. Параметры α, β, γ оптимизируются автоматически<br>
          4. Сезонный период = 12 месяцев<br><br>
          <strong>Формула:</strong><br>
          • Уровень: Lₜ = α×(Yₜ/Sₜ₋ₚ) + (1-α)×(Lₜ₋₁ + Tₜ₋₁)<br>
          • Тренд: Tₜ = β×(Lₜ - Lₜ₋₁) + (1-β)×Tₜ₋₁<br>
          • Сезонность: Sₜ = γ×(Yₜ/Lₜ) + (1-γ)×Sₜ₋ₚ<br>
          • Прогноз: Fₜ₊ₖ = (Lₜ + k×Tₜ) × Sₜ₊ₖ₋ₚ<br><br>
          <strong>Преимущества:</strong><br>
          • Учитывает сезонные колебания<br>
          • Самый точный метод для сезонных данных<br>
          • Адаптируется к изменениям тренда и сезонности
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
      analytics, forecastData, detectedAnomalies, loading, applyingChanges, selectedPeriod, selectedDate, selectedYear,
      includeAnomalies, showResultModal, resultModal, processedTotals, categorySpending, financialHealth, balanceClass,
      currentMonthName, periodLabel, localAnomaliesTotalAmount, hasLocalChanges, maxDailyForecast, formatMoney,
      formatMoneyAmount, formatExchangeRate, formatDate, formatDay, formatChange, getTrendIcon, getTrendText,
      getPaymentMethodText, getMapeClass, getMapeDesc, getShortDay, getMethodDescription, getBarHeight, getCategoryPercent,
      getLocalAnomalyStatus, hasLocalChange, toggleLocalAnomalyStatus, resetLocalChanges, applyAnomalyChanges, fetchAnalytics
    }
  }
}
</script>

<style scoped>
@import '../css/analytics.css';
@import '../css/analytics.css';

/* Глобальные стили для страницы */
.analytics-page {
  overflow-x: visible !important;
  overflow-y: visible !important;
}

.period-tabs { display: flex; gap: 4px; background: #f1f3f5; padding: 4px; border-radius: 12px; }
.period-tab { padding: 8px 16px; border: none; background: transparent; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; color: #6c757d; }
.period-tab.active { background: white; color: #1a1a2e; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.period-tab:hover:not(.active) { background: #e9ecef; }
.date-input, .year-input { padding: 8px 12px; border: 1px solid #e9ecef; border-radius: 10px; font-size: 14px; background: white; }

.categories-section { margin-top: 32px; background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
.section-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
.section-header h2 { font-size: 20px; font-weight: 600; margin: 0; }
.section-header-right { display: flex; gap: 16px; align-items: center; }
.total-expenses { font-size: 16px; font-weight: 600; color: #1a1a2e; }
.period-badge { font-size: 13px; padding: 4px 12px; background: #e9ecef; border-radius: 20px; color: #6c757d; }
.categories-table-container { overflow-x: auto; padding: 0 24px 24px 24px; }
.categories-table { width: 100%; border-collapse: collapse; }
.categories-table th { text-align: left; padding: 14px 12px; background: #f8f9fa; font-weight: 600; color: #495057; border-bottom: 2px solid #e9ecef; }
.categories-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
.category-name-cell { display: flex; align-items: center; gap: 10px; font-weight: 500; }
.category-color { width: 12px; height: 12px; border-radius: 4px; }
.category-amount-cell { font-weight: 600; color: #1a1a2e; }
.category-percent-cell { color: #6c757d; }
.category-progress-cell { width: 200px; }
.table-progress { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
.table-progress-bar { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
.empty-categories { text-align: center; padding: 60px 24px; }
.empty-icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5; }

.anomalies-section {
  margin-top: 32px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  overflow: visible;
}
.anomalies-count { font-size: 13px; padding: 4px 12px; background: #ef4444; color: white; border-radius: 20px; font-weight: 500; }
.anomalies-table-container { overflow-x: auto; }
.anomalies-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.anomalies-table th { text-align: left; padding: 14px 16px; background: #f8f9fa; font-weight: 600; color: #495057; border-bottom: 2px solid #e9ecef; }
.anomalies-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
.anomalies-table tr:hover { background: #f8f9fa; }
.anomalies-table tr.anomaly-changed { background: #fff3e0; }
.checkbox-cell { text-align: center; width: 40px; }
.checkbox-cell input { width: 18px; height: 18px; cursor: pointer; }
.description-cell { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.category-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.payment-method-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.payment-method-badge.cash { background: #e8f5e9; color: #2e7d32; }
.payment-method-badge.card { background: #e3f2fd; color: #1565c0; }
.payment-method-badge.transfer { background: #fff3e0; color: #ef6c00; }
.amount-cell .original-amount { color: #6c757d; font-size: 12px; }
.rate-cell { color: #6c757d; font-size: 13px; }
.byn-cell strong { color: #1a1a2e; font-weight: 600; }
.anomalies-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.anomalies-total strong { font-size: 18px; color: #ef4444; }
.anomalies-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
.anomalies-hint { font-size: 12px; color: #6c757d; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.hint-note { font-size: 11px; color: #6c84a3; background: #eef2f7; padding: 2px 8px; border-radius: 12px; }
.action-buttons { display: flex; gap: 12px; }
.btn-apply { padding: 8px 20px; background: #4caf50; color: white; border: none; border-radius: 10px; cursor: pointer; }
.btn-apply:hover:not(:disabled) { background: #45a049; }
.btn-apply:disabled { background: #a5d6a7; cursor: not-allowed; }
.btn-cancel { padding: 8px 20px; background: #f44336; color: white; border: none; border-radius: 10px; cursor: pointer; }
.btn-cancel:hover { background: #d32f2f; }

.forecast-section {
  margin-top: 32px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  overflow: visible;
}

.metrics-quality {
  display: flex;
  gap: 16px;
  padding: 20px 24px;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  flex-wrap: wrap;
  overflow: visible;
  position: relative;
  z-index: 1;
}

/* Карточка с тултипом внизу */
.quality-card {
  flex: 1;
  min-width: 120px;
  text-align: center;
  padding: 12px;
  background: white;
  border-radius: 12px;
  position: relative;
  cursor: help;
  overflow: visible;
  z-index: 1;
  transition: z-index 0s;
}

.quality-card:hover {
  z-index: 10000;
}

.quality-card:hover .card-tooltip {
  display: block;
}

.card-tooltip {
  display: none;
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: #1a1a2e;
  color: white;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 12px;
  width: 320px;
  max-width: 90vw;
  text-align: left;
  z-index: 10001;
  margin-top: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  white-space: normal;
  word-wrap: break-word;
  line-height: 1.4;
}

.card-tooltip::before {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 8px solid transparent;
  border-right: 8px solid transparent;
  border-bottom: 8px solid #1a1a2e;
}

.card-tooltip strong {
  display: block;
  margin-bottom: 8px;
  color: #4caf50;
  font-size: 13px;
}

.card-tooltip .tooltip-sub {
  display: block;
  margin-top: 8px;
  color: #adb5bd;
  font-size: 11px;
  line-height: 1.4;
}

/* Альтернативное позиционирование для крайних карточек */
.quality-card:first-child .card-tooltip {
  left: 0;
  transform: translateX(0);
}

.quality-card:first-child .card-tooltip::before {
  left: 20px;
  transform: translateX(0);
}

.quality-card:last-child .card-tooltip {
  left: auto;
  right: 0;
  transform: translateX(0);
}

.quality-card:last-child .card-tooltip::before {
  left: auto;
  right: 20px;
  transform: translateX(0);
}

.quality-label {
  font-size: 12px;
  color: #6c757d;
  display: block;
  margin-bottom: 4px;
}

.quality-value {
  font-size: 20px;
  font-weight: 700;
  display: block;
}

.quality-value.excellent { color: #4caf50; }
.quality-value.good { color: #8bc34a; }
.quality-value.normal { color: #ff9800; }
.quality-value.bad { color: #f44336; }
.quality-value.high { color: #4caf50; }
.quality-value.medium { color: #ff9800; }
.quality-value.low { color: #f44336; }

.quality-desc {
  font-size: 11px;
  color: #adb5bd;
}

.forecast-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; padding: 24px; }
.forecast-card { background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%); border-radius: 16px; padding: 20px; border: 1px solid #e9ecef; }
.forecast-card h3 { font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #495057; }
.forecast-amount { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; }
.forecast-detail { font-size: 13px; color: #6c757d; margin-bottom: 8px; }
.forecast-trend { font-size: 13px; font-weight: 500; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e9ecef; }
.forecast-trend.growth { color: #f44336; }
.forecast-trend.decline { color: #4caf50; }

.daily-forecast { padding: 0 24px 24px 24px; }
.daily-forecast h3 { font-size: 18px; font-weight: 600; margin-bottom: 20px; }
.daily-scroll-container { overflow-x: auto; }
.daily-chart { background: #f8f9fa; border-radius: 16px; padding: 20px; min-width: max-content; }
.chart-bars { display: flex; align-items: flex-end; gap: 8px; height: 220px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e9ecef; }
.bar-item { width: 60px; background: linear-gradient(180deg, #3498db 0%, #2980b9 100%); border-radius: 8px 8px 4px 4px; position: relative; cursor: pointer; transition: all 0.2s; }
.bar-item:hover { transform: translateY(-4px); background: linear-gradient(180deg, #e74c3c 0%, #c0392b 100%); }
.bar-item:hover .bar-tooltip { display: flex; }
.bar-tooltip { display: none; position: absolute; top: -80px; left: 50%; transform: translateX(-50%); background: #1a1a2e; color: white; padding: 10px 14px; border-radius: 10px; font-size: 12px; flex-direction: column; align-items: center; gap: 4px; white-space: nowrap; z-index: 100; }
.bar-tooltip::after { content: ''; position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 6px solid #1a1a2e; }
.tooltip-date { font-weight: 600; }
.tooltip-amount { color: #4caf50; font-weight: 600; }
.chart-labels { display: flex; gap: 8px; }
.label-item { width: 60px; text-align: center; display: flex; flex-direction: column; gap: 4px; }
.label-day { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.label-week { font-size: 11px; color: #6c757d; }

.category-forecast { padding: 0 24px 24px 24px; }
.category-forecast h3 { font-size: 18px; font-weight: 600; margin-bottom: 16px; }
.category-forecast.second-month { margin-top: 24px; }
.category-forecast-table { background: #f8f9fa; border-radius: 16px; overflow: hidden; }
.table-header { display: grid; grid-template-columns: 1fr 120px 100px 120px; padding: 14px 20px; background: #e9ecef; font-size: 13px; font-weight: 600; color: #495057; }
.table-body { max-height: 400px; overflow-y: auto; }
.table-row { display: grid; grid-template-columns: 1fr 120px 100px 120px; padding: 12px 20px; border-bottom: 1px solid #e9ecef; align-items: center; }
.table-row:hover { background: #fff; }
.row-category { display: flex; align-items: center; gap: 10px; }
.cat-dot { width: 10px; height: 10px; border-radius: 3px; }
.row-forecast { font-weight: 600; color: #1a1a2e; }
.row-daily { color: #6c757d; font-size: 13px; }
.row-share { display: flex; align-items: center; gap: 10px; }
.share-bar { flex: 1; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
.share-fill { height: 100%; border-radius: 3px; }
.share-value { font-size: 13px; font-weight: 500; min-width: 40px; }
.reliability-message { padding: 16px 24px; background: #fff3e0; border-top: 1px solid #ffe0b2; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #e65100; }
.message-icon { font-size: 16px; }

@media (max-width: 768px) {
  .forecast-grid { grid-template-columns: 1fr; }
  .table-header, .table-row { grid-template-columns: 1fr 100px 80px 100px; font-size: 12px; padding: 10px 12px; }
  .anomalies-footer { flex-direction: column; align-items: stretch; }
  .anomalies-actions { align-items: stretch; }
  .action-buttons { flex-direction: column; }
  .btn-apply, .btn-cancel { width: 100%; }
  .bar-item, .label-item { width: 45px; }
  .categories-table-container { padding: 0 16px 16px 16px; }
  .categories-table th, .categories-table td { padding: 10px 8px; }
  .card-tooltip { width: 260px; max-width: 85vw; }
  .quality-card:first-child .card-tooltip,
  .quality-card:last-child .card-tooltip {
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }
  .quality-card:first-child .card-tooltip::before,
  .quality-card:last-child .card-tooltip::before {
    left: 50%;
    right: auto;
    transform: translateX(-50%);
  }
}
</style>