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
import { useAnalytics } from '../composables/useAnalytics'

export default {
  name: 'AnalyticsPage',
  setup() {
    return useAnalytics()
  }
}
</script>

<style scoped>
@import '../css/analytics.css';
</style>