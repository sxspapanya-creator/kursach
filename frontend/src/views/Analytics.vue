<template>
  <div class="analytics-page">
    <!-- Заголовок и управление периодом -->
    <div class="header">
      <h1>📊 Аналитика бюджета</h1>
      <div class="period-controls">
        <select v-model="selectedPeriod" @change="fetchAnalytics">
          <option value="month">Месяц</option>
          <option value="week">Неделя</option>
          <option value="year">Год</option>
        </select>
        <input v-model="selectedDate" type="month" @change="fetchAnalytics">
        <button @click="fetchAnalytics" :disabled="loading" class="refresh-btn">
          {{ loading ? 'Обновление...' : '🔄 Обновить' }}
        </button>
      </div>
    </div>

    <!-- Индикатор загрузки -->
    <div v-if="loading" class="loading-overlay">
      <div class="spinner"></div>
      <p>Анализируем ваши финансы...</p>
    </div>

    <!-- Основные метрики -->
    <div class="metrics-grid">
      <!-- Финансовое здоровье -->
      <div class="metric-card health-card" :style="{ borderColor: financialHealth.color }">
        <div class="metric-icon">❤️</div>
        <div class="metric-content">
          <h3>Финансовое здоровье</h3>
          <div class="metric-value">{{ financialHealth.score }}/100</div>
          <div class="metric-label">{{ financialHealth.status_label }}</div>
          <div class="health-progress">
            <div class="progress-bar" :style="{ width: financialHealth.score + '%', backgroundColor: financialHealth.color }"></div>
          </div>
        </div>
      </div>

      <!-- Доходы -->
      <div class="metric-card income-card">
        <div class="metric-icon">📈</div>
        <div class="metric-content">
          <h3>Доходы</h3>
          <div class="metric-value">{{ formatMoney(analytics.totals?.income) }}</div>
          <div class="metric-label">{{ analytics.date_range?.label || 'За период' }}</div>
          <div class="trend-indicator" v-if="forecasts.incomeTrend">
            <span :class="['trend-dot', forecasts.incomeTrend]"></span>
            {{ forecasts.incomeTrendLabel }}
          </div>
        </div>
      </div>

      <!-- Расходы -->
      <div class="metric-card expense-card">
        <div class="metric-icon">📉</div>
        <div class="metric-content">
          <h3>Расходы</h3>
          <div class="metric-value">{{ formatMoney(analytics.totals?.expenses) }}</div>
          <div class="metric-label">{{ analytics.date_range?.label || 'За период' }}</div>
          <div class="trend-indicator" v-if="forecasts.expenseTrend">
            <span :class="['trend-dot', forecasts.expenseTrend]"></span>
            {{ forecasts.expenseTrendLabel }}
          </div>
        </div>
      </div>

      <!-- Баланс -->
      <div class="metric-card balance-card" :class="balanceClass">
        <div class="metric-icon">⚖️</div>
        <div class="metric-content">
          <h3>Баланс</h3>
          <div class="metric-value">{{ formatMoney(analytics.totals?.balance) }}</div>
          <div class="metric-label">чистый остаток</div>
          <div class="balance-diff" v-if="analytics.totals?.balance">
            {{ analytics.totals.balance > 0 ? '+' : '' }}{{ formatMoney(analytics.totals.balance) }}
          </div>
        </div>
      </div>

      <!-- Норма сбережений -->
      <div class="metric-card savings-card">
        <div class="metric-icon">💰</div>
        <div class="metric-content">
          <h3>Норма сбережений</h3>
          <div class="metric-value">{{ analytics.totals?.savings_rate?.toFixed(1) || '0' }}%</div>
          <div class="metric-label">от дохода</div>
          <div class="savings-progress">
            <div class="progress-bar" :style="{ width: Math.min(analytics.totals?.savings_rate || 0, 100) + '%' }"></div>
            <div class="progress-markers">
              <span class="marker poor" title="Плохо (<10%)"></span>
              <span class="marker good" title="Хорошо (10-20%)"></span>
              <span class="marker excellent" title="Отлично (>20%)"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Точность прогнозов -->
      <div class="metric-card accuracy-card">
        <div class="metric-icon">🎯</div>
        <div class="metric-content">
          <h3>Точность прогнозов</h3>
          <div class="metric-value">R² = {{ forecasts.accuracy.toFixed(3) }}</div>
          <div class="metric-label">коэффициент детерминации</div>
          <div class="accuracy-scale">
            <div class="scale-labels">
              <span>Низкая</span>
              <span>Высокая</span>
            </div>
            <div class="scale-bar">
              <div class="scale-fill" :style="{ width: Math.min(forecasts.accuracy * 100, 100) + '%' }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Прогноз кассового разрыва -->
    <div class="cash-gap-section" v-if="analytics.forecasts">
      <h2>💰 Прогноз кассового разрыва</h2>
      <div class="cash-gap-cards">
        <div class="cash-gap-card">
          <div class="card-icon">🏦</div>
          <div class="card-content">
            <h4>Текущий баланс</h4>
            <div class="card-value">{{ formatMoney(analytics.totals?.balance) }}</div>
          </div>
        </div>

        <div class="cash-gap-card">
          <div class="card-icon">➕</div>
          <div class="card-content">
            <h4>Прогноз доходов</h4>
            <div class="card-value">{{ formatMoney(analytics.forecasts?.next_month_income) }}</div>
            <div class="card-trend" :class="forecasts.incomeTrend">
              {{ forecasts.incomeTrendLabel }}
            </div>
          </div>
        </div>

        <div class="cash-gap-card">
          <div class="card-icon">➖</div>
          <div class="card-content">
            <h4>Прогноз расходов</h4>
            <div class="card-value">{{ formatMoney(analytics.forecasts?.next_month_expense) }}</div>
            <div class="card-trend" :class="forecasts.expenseTrend">
              {{ forecasts.expenseTrendLabel }}
            </div>
          </div>
        </div>

        <div class="cash-gap-card result-card" :class="cashGapStatus">
          <div class="card-icon">
            <span v-if="cashGapStatus === 'critical'">⚠️</span>
            <span v-else-if="cashGapStatus === 'warning'">🔔</span>
            <span v-else>✅</span>
          </div>
          <div class="card-content">
            <h4>Итоговый прогноз</h4>
            <div class="card-value">{{ formatMoney(cashGapForecast) }}</div>
            <div class="cash-gap-status">{{ cashGapMessage }}</div>
          </div>
        </div>
      </div>

      <!-- Рекомендуемые лимиты по категориям расходов -->
      <div class="optimal-distribution" v-if="expenseCategoriesDistribution.length > 0">
        <button @click="showOptimalDistribution = !showOptimalDistribution" class="toggle-distribution">
          🎯 {{ showOptimalDistribution ? 'Скрыть' : 'Показать' }} рекомендуемые лимиты по категориям
        </button>

        <div v-if="showOptimalDistribution" class="distribution-table">
          <h3>Рекомендуемые лимиты по категориям расходов</h3>
          <table>
            <thead>
            <tr>
              <th>Категория</th>
              <th>Текущая средняя</th>
              <th>Рекомендуемый лимит</th>
              <th>Стабильность</th>
              <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in expenseCategoriesDistribution" :key="item.category_id">
              <td>{{ item.category_name }}</td>
              <td>{{ formatMoney(item.current_monthly_avg) }}</td>
              <td class="recommended-limit">{{ formatMoney(item.recommended_limit) }}</td>
              <td>
                <div class="stability-indicator">
                  <div class="stability-bar">
                    <div class="stability-fill" :style="{ width: item.stability_score + '%' }"></div>
                  </div>
                  <span class="stability-value">{{ item.stability_score.toFixed(0) }}%</span>
                </div>
              </td>
              <td>
                <button @click="applyOptimalLimit(item)" class="apply-limit-btn">
                  Применить
                </button>
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Тренды расходов - НОВЫЙ ЛИНЕЙНЫЙ ГРАФИК -->
    <div class="trends-section" v-if="analytics.trends && analytics.trends.weighted_moving_average && analytics.trends.weighted_moving_average.length">
      <h2>📊 Анализ трендов расходов</h2>

      <!-- Линейный график: Фактические расходы vs Взвешенное среднее -->
      <div class="line-chart-container">
        <div class="chart-header">
          <div class="chart-title">Взвешенное скользящее среднее расходов</div>
          <div class="chart-period">
            Последние {{ analytics.trends.weighted_moving_average.length }} месяцев
          </div>
        </div>

        <div class="line-chart-wrapper">
          <!-- Y-ось (значения) -->
          <div class="line-chart-y-axis">
            <div v-for="(tick, index) in lineChartYTicks" :key="index" class="y-tick">
              {{ formatShortMoney(tick) }}
            </div>
          </div>

          <!-- Область графика -->
          <div class="line-chart-area" ref="chartArea">
            <!-- Сетка графика -->
            <div class="chart-grid">
              <div v-for="(tick, index) in lineChartYTicks" :key="'grid-' + index"
                   class="grid-line horizontal"
                   :style="{ top: getGridLinePosition(tick) + 'px' }"></div>
              <div v-for="(month, index) in lineChartMonths" :key="'v-grid-' + index"
                   class="grid-line vertical"
                   :style="{ left: getMonthPosition(index) + 'px' }"></div>
            </div>

            <!-- Линия фактических расходов -->
            <svg class="trend-line-svg" :width="chartWidth" :height="chartHeight">
              <!-- Линия фактических расходов -->
              <path
                  :d="actualLinePath"
                  fill="none"
                  stroke="#e74c3c"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="trend-line actual-line"
              />

              <!-- Линия взвешенного среднего -->
              <path
                  :d="weightedLinePath"
                  fill="none"
                  stroke="#3498db"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-dasharray="5, 5"
                  class="trend-line weighted-line"
              />

              <!-- Точки фактических расходов -->
              <g v-for="(point, index) in actualPoints" :key="'actual-' + index">
                <circle
                    :cx="point.x"
                    :cy="point.y"
                    r="6"
                    fill="#e74c3c"
                    stroke="white"
                    stroke-width="2"
                    class="data-point"
                    @mouseenter="showTooltip($event, point, 'actual', index)"
                    @mouseleave="hideTooltip"
                />
              </g>

              <!-- Точки взвешенного среднего -->
              <g v-for="(point, index) in weightedPoints" :key="'weighted-' + index">
                <circle
                    :cx="point.x"
                    :cy="point.y"
                    r="6"
                    fill="#3498db"
                    stroke="white"
                    stroke-width="2"
                    class="data-point weighted-point"
                    @mouseenter="showTooltip($event, point, 'weighted', index)"
                    @mouseleave="hideTooltip"
                />
              </g>
            </svg>

            <!-- Подписи месяцев (X-ось) -->
            <div class="x-axis-labels">
              <div v-for="(month, index) in lineChartMonths"
                   :key="'label-' + index"
                   class="month-label"
                   :style="{ left: getMonthPosition(index) + 'px' }">
                {{ formatMonthShort(month) }}
              </div>
            </div>
          </div>

          <!-- Тулутип -->
          <div v-if="tooltip.show" class="line-chart-tooltip"
               :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }">
            <div class="tooltip-header">
              <strong>{{ tooltip.month }}</strong>
            </div>
            <div class="tooltip-content">
              <div v-if="tooltip.type === 'actual'" class="tooltip-item">
                <span class="tooltip-dot actual"></span>
                Фактические расходы: <strong>{{ formatMoney(tooltip.value) }}</strong>
              </div>
              <div v-if="tooltip.type === 'weighted'" class="tooltip-item">
                <span class="tooltip-dot weighted"></span>
                Взвешенное среднее: <strong>{{ formatMoney(tooltip.value) }}</strong>
              </div>
              <div v-if="tooltip.type === 'both'" class="tooltip-item">
                <span class="tooltip-dot actual"></span>
                Фактические расходы: <strong>{{ formatMoney(tooltip.actualValue) }}</strong>
              </div>
              <div v-if="tooltip.type === 'both'" class="tooltip-item">
                <span class="tooltip-dot weighted"></span>
                Взвешенное среднее: <strong>{{ formatMoney(tooltip.weightedValue) }}</strong>
              </div>
              <div v-if="tooltip.difference" class="tooltip-difference"
                   :class="{ positive: tooltip.difference > 0, negative: tooltip.difference < 0 }">
                Разница: {{ tooltip.difference > 0 ? '+' : '' }}{{ formatMoney(tooltip.difference) }}
                ({{ tooltip.differencePercentage > 0 ? '+' : '' }}{{ tooltip.differencePercentage.toFixed(1) }}%)
              </div>
            </div>
          </div>
        </div>

        <!-- Статистика тренда -->
        <div class="trend-summary">
          <div class="trend-direction">
            Направление тренда:
            <strong :class="trendDirectionClass">{{ trendDirectionLabel }}</strong>
          </div>
          <div class="trend-stats">
            <div class="stat-item">
              <span class="stat-label">Средние расходы:</span>
              <span class="stat-value">{{ formatMoney(averageExpenses) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Максимальные расходы:</span>
              <span class="stat-value">{{ formatMoney(maxExpenses) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Минимальные расходы:</span>
              <span class="stat-value">{{ formatMoney(minExpenses) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Изменение за период:</span>
              <span class="stat-value" :class="{ positive: trendChange > 0, negative: trendChange < 0 }">
                {{ trendChange > 0 ? '+' : '' }}{{ formatMoney(trendChange) }}
                ({{ trendChangePercentage > 0 ? '+' : '' }}{{ trendChangePercentage.toFixed(1) }}%)
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Подробная статистика линейной регрессии -->
    <div class="regression-stats-detailed" v-if="analytics.forecasts">
      <h2>📐 Детальная статистика линейной регрессии</h2>

      <div class="regression-container">
        <!-- Статистика по доходам -->
        <div class="regression-card">
          <h3>📈 Регрессия доходов</h3>
          <div class="regression-equation">
            <strong>Уравнение:</strong> y = a + b·x<br>
            <span class="equation">y = {{ formatNumber(analytics.forecasts.income_regression?.a || 0) }}
            + {{ formatNumber(analytics.forecasts.income_regression?.b || 0) }}·x</span>
          </div>

          <div class="regression-details">
            <div class="regression-detail">
              <span class="detail-label">Коэффициент a (intercept):</span>
              <span class="detail-value">{{ formatNumber(analytics.forecasts.income_regression?.a || 0) }}</span>
              <div class="detail-description">Базовый уровень доходов при x=0</div>
            </div>

            <div class="regression-detail">
              <span class="detail-label">Коэффициент b (slope):</span>
              <span class="detail-value">{{ formatNumber(analytics.forecasts.income_regression?.b || 0) }}</span>
              <div class="detail-description">
                <span v-if="(analytics.forecasts.income_regression?.b || 0) > 100">
                  ↗️ Положительный тренд: доходы вырастут на {{ formatNumber(analytics.forecasts.income_regression?.b || 0) }} Br/мес
                </span>
                <span v-else-if="(analytics.forecasts.income_regression?.b || 0) < -100">
                  ↘️ Отрицательный тренд: доходы снизятся на {{ formatNumber(Math.abs(analytics.forecasts.income_regression?.b || 0)) }} Br/мес
                </span>
                <span v-else>
                  → Стабильный тренд: незначительные изменения
                </span>
              </div>
            </div>

            <div class="regression-detail">
              <span class="detail-label">Коэффициент детерминации R²:</span>
              <span class="detail-value r-squared">{{ (analytics.forecasts.income_regression?.r_squared || 0).toFixed(4) }}</span>
              <div class="detail-description">
                <span v-if="(analytics.forecasts.income_regression?.r_squared || 0) >= 0.8">
                  ✅ Высокая точность прогноза (80%+)
                </span>
                <span v-else-if="(analytics.forecasts.income_regression?.r_squared || 0) >= 0.5">
                  ⚠️ Средняя точность прогноза (50-80%)
                </span>
                <span v-else>
                  ❌ Низкая точность прогноза (<50%)
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Статистика по расходам -->
        <div class="regression-card">
          <h3>📉 Регрессия расходов</h3>
          <div class="regression-equation">
            <strong>Уравнение:</strong> y = a + b·x<br>
            <span class="equation">y = {{ formatNumber(analytics.forecasts.expense_regression?.a || 0) }}
            + {{ formatNumber(analytics.forecasts.expense_regression?.b || 0) }}·x</span>
          </div>

          <div class="regression-details">
            <div class="regression-detail">
              <span class="detail-label">Коэффициент a (intercept):</span>
              <span class="detail-value">{{ formatNumber(analytics.forecasts.expense_regression?.a || 0) }}</span>
              <div class="detail-description">Базовый уровень расходов при x=0</div>
            </div>

            <div class="regression-detail">
              <span class="detail-label">Коэффициент b (slope):</span>
              <span class="detail-value">{{ formatNumber(analytics.forecasts.expense_regression?.b || 0) }}</span>
              <div class="detail-description">
                <span v-if="(analytics.forecasts.expense_regression?.b || 0) > 100">
                  ↗️ Положительный тренд: расходы вырастут на {{ formatNumber(analytics.forecasts.expense_regression?.b || 0) }} Br/мес
                </span>
                <span v-else-if="(analytics.forecasts.expense_regression?.b || 0) < -100">
                  ↘️ Отрицательный тренд: расходы снизятся на {{ formatNumber(Math.abs(analytics.forecasts.expense_regression?.b || 0)) }} Br/мес
                </span>
                <span v-else>
                  → Стабильный тренд: незначительные изменения
                </span>
              </div>
            </div>

            <div class="regression-detail">
              <span class="detail-label">Коэффициент детерминации R²:</span>
              <span class="detail-value r-squared">{{ (analytics.forecasts.expense_regression?.r_squared || 0).toFixed(4) }}</span>
              <div class="detail-description">
                <span v-if="(analytics.forecasts.expense_regression?.r_squared || 0) >= 0.8">
                  ✅ Высокая точность прогноза (80%+)
                </span>
                <span v-else-if="(analytics.forecasts.expense_regression?.r_squared || 0) >= 0.5">
                  ⚠️ Средняя точность прогноза (50-80%)
                </span>
                <span v-else>
                  ❌ Низкая точность прогноза (<50%)
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Интерпретация результатов -->
      <div class="regression-interpretation">
        <h4>📊 Интерпретация результатов регрессии</h4>
        <div class="interpretation-content">
          <p><strong>Коэффициент детерминации (R²):</strong> показывает, насколько хорошо линейная модель описывает данные.
            Значение от 0 до 1, где 1 означает идеальное соответствие.</p>
          <p><strong>Коэффициент b (slope):</strong> показывает изменение показателя за один период времени.
            Положительное значение означает рост, отрицательное - снижение.</p>
          <p><strong>Коэффициент a (intercept):</strong> базовое значение показателя в начале анализа.</p>
        </div>
      </div>
    </div>

    <!-- Расходы по категориям -->
    <div class="category-analysis" v-if="analytics.category_spending && analytics.category_spending.length">
      <div class="section-header">
        <h2>📋 Анализ расходов по категориям</h2>
        <div class="total-summary">
          Всего расходов: <strong>{{ formatMoney(analytics.totals?.expenses || 0) }}</strong>
          <span class="category-count">({{ analytics.category_spending.length }} категорий)</span>
        </div>
      </div>

      <div class="analysis-vertical">
        <!-- Круговая диаграмма -->
        <div class="pie-chart-section">
          <h3>Распределение расходов</h3>
          <div class="pie-chart-container">
            <div class="pie-chart">
              <svg width="200" height="200" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="80" fill="none" stroke="#f0f0f0" stroke-width="40" />

                <g v-for="(category, index) in analytics.category_spending" :key="category.id">
                  <circle
                      cx="100"
                      cy="100"
                      r="80"
                      fill="none"
                      :stroke="category.color || getCategoryColor(index)"
                      stroke-width="40"
                      :stroke-dasharray="getDashArray(category)"
                      :stroke-dashoffset="getDashOffset(category, index)"
                      class="pie-segment"
                      @mouseenter="hoveredCategory = category"
                      @mouseleave="hoveredCategory = null"
                  />
                </g>

                <text x="100" y="95" text-anchor="middle" class="pie-center-text">
                  {{ analytics.category_spending.length }}
                </text>
                <text x="100" y="115" text-anchor="middle" class="pie-center-subtext">
                  категорий
                </text>
              </svg>
            </div>

            <div class="pie-legend">
              <div
                  v-for="(category, index) in analytics.category_spending.slice(0, 5)"
                  :key="category.id"
                  class="legend-item"
                  @mouseenter="hoveredCategory = category"
                  @mouseleave="hoveredCategory = null"
                  :class="{ active: hoveredCategory?.id === category.id }"
              >
                <div class="legend-color" :style="{ backgroundColor: category.color || getCategoryColor(index) }"></div>
                <div class="legend-text">
                  <span class="legend-name">{{ category.name }}</span>
                  <span class="legend-value">{{ formatMoney(category.total) }}</span>
                </div>
                <div class="legend-percentage">{{ getCategoryPercentage(category.total) }}%</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Таблица категорий -->
        <div class="category-table-section">
          <h3>Детализация по категориям</h3>
          <div class="table-container">
            <table class="categories-table">
              <thead>
              <tr>
                <th class="col-category">Категория</th>
                <th class="col-amount">Сумма</th>
                <th class="col-limit">Лимит</th>
                <th class="col-status">Статус</th>
                <th class="col-trend">Тренд</th>
              </tr>
              </thead>
              <tbody>
              <tr
                  v-for="category in analytics.category_spending"
                  :key="category.id"
                  @mouseenter="hoveredCategory = category"
                  @mouseleave="hoveredCategory = null"
                  :class="{ highlighted: hoveredCategory?.id === category.id }"
              >
                <td class="col-category">
                  <div class="category-info">
                    <div
                        class="category-color"
                        :style="{ backgroundColor: category.color || '#3498db' }"
                    ></div>
                    <span class="category-name">{{ category.name }}</span>
                  </div>
                </td>
                <td class="col-amount">
                  <div class="amount-value">{{ formatMoney(category.total) }}</div>
                  <div class="amount-average">
                    в среднем {{ formatMoney(category.average_monthly || 0) }}/мес
                  </div>
                </td>
                <td class="col-limit">
                    <span v-if="category.budget_limit" class="limit-value">
                      {{ formatMoney(category.budget_limit) }}
                    </span>
                  <span v-else class="no-limit">Не задан</span>

                  <div v-if="category.limit_percentage" class="limit-progress">
                    <div class="progress-bar">
                      <div
                          class="progress-fill"
                          :class="category.budget_status"
                          :style="{ width: Math.min(category.limit_percentage, 100) + '%' }"
                      ></div>
                    </div>
                    <span class="progress-text">{{ category.limit_percentage.toFixed(1) }}%</span>
                  </div>
                </td>
                <td class="col-status">
                  <div class="status-badge" :class="category.budget_status">
                    {{ getBudgetStatusLabel(category.budget_status) }}
                  </div>
                </td>
                <td class="col-trend">
                  <div v-if="getCategoryTrend(category)" class="trend-indicator">
                    <span :class="['trend-arrow', getCategoryTrend(category)]"></span>
                    {{ getTrendLabel(getCategoryTrend(category)) }}
                  </div>
                  <span v-else class="no-trend">—</span>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

export default {
  name: 'AnalyticsPage',
  setup() {
    const analytics = ref({
      totals: {},
      category_spending: [],
      recommendations: [],
      date_range: {},
      forecasts: {},
      trends: {
        weighted_moving_average: [],
        actual_data: []
      },
      financial_health: {},
      largest_transactions: {
        expenses: [],
        incomes: []
      }
    })

    const loading = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const showOptimalDistribution = ref(false)
    const hoveredCategory = ref(null)
    const chartArea = ref(null)
    const tooltip = ref({
      show: false,
      x: 0,
      y: 0,
      month: '',
      value: 0,
      type: '', // 'actual', 'weighted', 'both'
      actualValue: 0,
      weightedValue: 0,
      difference: 0,
      differencePercentage: 0
    })

    // Размеры графика
    const chartWidth = ref(800)
    const chartHeight = ref(400)
    const padding = {
      top: 40,
      right: 40,
      bottom: 60,
      left: 60
    }

    // Вычисляемые свойства
    const balanceClass = computed(() => {
      const balance = analytics.value.totals?.balance || 0
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    const financialHealth = computed(() => {
      return analytics.value.financial_health || {
        score: 0,
        status: 'poor',
        status_label: 'Не определено',
        color: '#95a5a6'
      }
    })

    const forecasts = computed(() => {
      const incomeForecast = analytics.value.forecasts?.income_regression || {}
      const expenseForecast = analytics.value.forecasts?.expense_regression || {}

      return {
        incomeTrend: incomeForecast.trend || 'stable',
        incomeTrendLabel: getTrendLabel(incomeForecast.trend),
        expenseTrend: expenseForecast.trend || 'stable',
        expenseTrendLabel: getTrendLabel(expenseForecast.trend),
        accuracy: Math.max(
            incomeForecast.r_squared || 0,
            expenseForecast.r_squared || 0
        )
      }
    })

    // Фильтрация категорий расходов для рекомендуемых лимитов
    const expenseCategoriesDistribution = computed(() => {
      if (!analytics.value.forecasts?.optimal_distribution) return []
      return analytics.value.forecasts.optimal_distribution.filter(item => {
        return item.category_name && !item.category_name.toLowerCase().includes('доход')
      })
    })

    // Данные для линейного графика
    const weightedData = computed(() => {
      return analytics.value.trends?.weighted_moving_average || []
    })

    const lineChartMonths = computed(() => {
      return weightedData.value.map(item => item.month)
    })

    const actualValues = computed(() => {
      return weightedData.value.map(item => item.actual || 0)
    })

    const weightedValues = computed(() => {
      return weightedData.value.map(item => item.weighted_average || 0)
    })

    // Минимальное и максимальное значение для масштабирования графика
    const maxExpenses = computed(() => {
      const allValues = [...actualValues.value, ...weightedValues.value]
      return Math.max(...allValues, 1)
    })

    const minExpenses = computed(() => {
      const allValues = [...actualValues.value, ...weightedValues.value]
      return Math.min(...allValues)
    })

    const averageExpenses = computed(() => {
      if (actualValues.value.length === 0) return 0
      const sum = actualValues.value.reduce((a, b) => a + b, 0)
      return sum / actualValues.value.length
    })

    const trendChange = computed(() => {
      if (actualValues.value.length < 2) return 0
      const first = actualValues.value[0]
      const last = actualValues.value[actualValues.value.length - 1]
      return last - first
    })

    const trendChangePercentage = computed(() => {
      if (actualValues.value.length < 2 || actualValues.value[0] === 0) return 0
      const first = actualValues.value[0]
      const last = actualValues.value[actualValues.value.length - 1]
      return ((last - first) / first) * 100
    })

    // Деления на Y-оси
    const lineChartYTicks = computed(() => {
      const max = maxExpenses.value
      const min = minExpenses.value
      const range = max - min
      const tickCount = 5
      const tickStep = range / (tickCount - 1)

      const ticks = []
      for (let i = 0; i < tickCount; i++) {
        ticks.push(Math.round(min + (tickStep * i)))
      }
      return ticks
    })

    // Позиционирование точек на графике
    const actualPoints = computed(() => {
      if (actualValues.value.length === 0) return []

      const points = []
      const xStep = (chartWidth.value - padding.left - padding.right) / (actualValues.value.length - 1)

      for (let i = 0; i < actualValues.value.length; i++) {
        const x = padding.left + (xStep * i)
        const y = getYPosition(actualValues.value[i])
        points.push({
          x,
          y,
          value: actualValues.value[i],
          month: lineChartMonths.value[i],
          monthIndex: i
        })
      }

      return points
    })

    const weightedPoints = computed(() => {
      if (weightedValues.value.length === 0) return []

      const points = []
      const xStep = (chartWidth.value - padding.left - padding.right) / (weightedValues.value.length - 1)

      for (let i = 0; i < weightedValues.value.length; i++) {
        const x = padding.left + (xStep * i)
        const y = getYPosition(weightedValues.value[i])
        points.push({
          x,
          y,
          value: weightedValues.value[i],
          month: lineChartMonths.value[i],
          monthIndex: i
        })
      }

      return points
    })

    // SVG path для линии фактических расходов
    const actualLinePath = computed(() => {
      if (actualPoints.value.length === 0) return ''

      let path = `M ${actualPoints.value[0].x} ${actualPoints.value[0].y}`

      for (let i = 1; i < actualPoints.value.length; i++) {
        const prevX = actualPoints.value[i-1].x
        const prevY = actualPoints.value[i-1].y
        const currX = actualPoints.value[i].x
        const currY = actualPoints.value[i].y

        // Контрольные точки для плавной кривой
        const cp1x = prevX + (currX - prevX) * 0.3
        const cp1y = prevY
        const cp2x = currX - (currX - prevX) * 0.3
        const cp2y = currY

        path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${currX} ${currY}`
      }

      return path
    })

    // SVG path для линии взвешенного среднего
    const weightedLinePath = computed(() => {
      if (weightedPoints.value.length === 0) return ''

      let path = `M ${weightedPoints.value[0].x} ${weightedPoints.value[0].y}`

      for (let i = 1; i < weightedPoints.value.length; i++) {
        const prevX = weightedPoints.value[i-1].x
        const prevY = weightedPoints.value[i-1].y
        const currX = weightedPoints.value[i].x
        const currY = weightedPoints.value[i].y

        const cp1x = prevX + (currX - prevX) * 0.3
        const cp1y = prevY
        const cp2x = currX - (currX - prevX) * 0.3
        const cp2y = currY

        path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${currX} ${currY}`
      }

      return path
    })

    const trendDirectionLabel = computed(() => {
      const trend = analytics.value.trends?.trend_direction
      switch(trend) {
        case 'growth': return 'Рост расходов ↑'
        case 'decline': return 'Снижение расходов ↓'
        case 'stable': return 'Стабильность →'
        default: return 'Недостаточно данных'
      }
    })

    const trendDirectionClass = computed(() => {
      const trend = analytics.value.trends?.trend_direction
      switch(trend) {
        case 'growth': return 'trend-up'
        case 'decline': return 'trend-down'
        case 'stable': return 'trend-stable'
        default: return ''
      }
    })

    const cashGapForecast = computed(() => {
      if (!analytics.value.totals || !analytics.value.forecasts) return 0
      const currentBalance = analytics.value.totals.balance || 0
      const forecastedIncome = analytics.value.forecasts.next_month_income || 0
      const forecastedExpense = analytics.value.forecasts.next_month_expense || 0
      return currentBalance + forecastedIncome - forecastedExpense
    })

    const cashGapStatus = computed(() => {
      const gap = cashGapForecast.value
      const expenses = analytics.value.totals?.expenses || 1
      if (gap < 0) return 'critical'
      if (gap < expenses * 0.3) return 'warning'
      return 'good'
    })

    const cashGapMessage = computed(() => {
      const gap = cashGapForecast.value
      if (gap < 0) {
        return `Прогнозируется дефицит ${formatMoney(Math.abs(gap))}`
      } else if (gap < (analytics.value.totals?.expenses || 1) * 0.3) {
        return `Запас прочности низкий: ${formatMoney(gap)}`
      } else {
        return `Финансовая устойчивость: ${formatMoney(gap)}`
      }
    })

    // Методы для позиционирования
    const getYPosition = (value) => {
      const max = maxExpenses.value
      const min = minExpenses.value
      const range = max - min || 1
      const normalized = (value - min) / range
      return chartHeight.value - padding.bottom - (normalized * (chartHeight.value - padding.top - padding.bottom))
    }

    const getGridLinePosition = (value) => {
      return getYPosition(value) - padding.top
    }

    const getMonthPosition = (index) => {
      if (lineChartMonths.value.length <= 1) return padding.left
      const xStep = (chartWidth.value - padding.left - padding.right) / (lineChartMonths.value.length - 1)
      return padding.left + (xStep * index)
    }

    const showTooltip = (event, point, type, index) => {
      const chartRect = chartArea.value?.getBoundingClientRect()
      if (!chartRect) return

      const monthData = weightedData.value[index]

      tooltip.value = {
        show: true,
        x: event.clientX - chartRect.left + 10,
        y: event.clientY - chartRect.top - 10,
        month: formatMonth(monthData.month),
        value: point.value,
        type: type,
        actualValue: monthData.actual || 0,
        weightedValue: monthData.weighted_average || 0,
        difference: monthData.actual - monthData.weighted_average,
        differencePercentage: monthData.weighted_average > 0 ?
            ((monthData.actual - monthData.weighted_average) / monthData.weighted_average) * 100 : 0
      }
    }

    const hideTooltip = () => {
      tooltip.value.show = false
    }

    // Методы
    const fetchAnalytics = async () => {
      try {
        loading.value = true

        const params = {
          period: selectedPeriod.value,
          month: selectedDate.value.split('-')[1],
          year: selectedDate.value.split('-')[0]
        }

        const response = await axios.get('/api/analytics/overview', { params })

        if (response.data.status === 'success') {
          const data = response.data.data || {}
          analytics.value = {
            totals: data.totals || {},
            category_spending: data.category_spending || [],
            recommendations: data.recommendations || [],
            date_range: data.date_range || {},
            forecasts: data.forecasts || {},
            trends: {
              weighted_moving_average: data.trends?.weighted_moving_average || [],
              actual_data: data.trends?.actual_data || [],
              trend_direction: data.trends?.trend_direction || 'stable'
            },
            financial_health: data.financial_health || {},
            largest_transactions: {
              expenses: data.largest_transactions?.expenses || [],
              incomes: data.largest_transactions?.incomes || []
            }
          }
        } else {
          analytics.value = response.data.data || {
            totals: {},
            category_spending: [],
            recommendations: [],
            date_range: {},
            forecasts: {},
            trends: {
              weighted_moving_average: [],
              actual_data: [],
              trend_direction: 'stable'
            },
            financial_health: {},
            largest_transactions: {
              expenses: [],
              incomes: []
            }
          }
          console.warn('Server returned error status:', response.data.message)
        }

      } catch (error) {
        console.error('Error fetching analytics:', error)
        analytics.value = {
          totals: {
            income: 0,
            expenses: 0,
            balance: 0,
            savings_rate: 0,
          },
          category_spending: [],
          recommendations: [{
            type: 'warning',
            title: '📊 Данные временно недоступны',
            message: 'Попробуйте обновить страницу или добавить транзакции'
          }],
          date_range: {
            start: new Date().toISOString().split('T')[0],
            end: new Date().toISOString().split('T')[0],
            label: 'Текущий месяц'
          },
          forecasts: {
            income_regression: { a: 0, b: 0, r_squared: 0, trend: 'stable', next_month: 0 },
            expense_regression: { a: 0, b: 0, r_squared: 0, trend: 'stable', next_month: 0 },
            next_month_income: 0,
            next_month_expense: 0,
            optimal_distribution: []
          },
          trends: {
            weighted_moving_average: [],
            actual_data: [],
            trend_direction: 'stable'
          },
          financial_health: {
            score: 0,
            status: 'poor',
            status_label: 'Нет данных',
            color: '#95a5a6'
          },
          largest_transactions: {
            expenses: [],
            incomes: []
          }
        }
      } finally {
        loading.value = false
        // После загрузки данных обновляем размеры графика
        nextTick(() => {
          updateChartDimensions()
        })
      }
    }

    const updateChartDimensions = () => {
      if (!chartArea.value) return
      const rect = chartArea.value.getBoundingClientRect()
      chartWidth.value = rect.width
      chartHeight.value = 400
    }

    const formatMoney = (amount) => {
      const num = Number(amount);

      if (isNaN(num) || !isFinite(num)) return '0 Br';

      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'Byn',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(num);
    }

    const formatNumber = (num) => {
      const number = Number(num);
      if (isNaN(number) || !isFinite(number)) return '0';

      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
      }).format(number);
    }

    const formatShortMoney = (amount) => {
      const num = Number(amount);

      if (isNaN(num) || !isFinite(num)) return '0';

      if (num === 0) return '0';

      if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
      if (num >= 1000) return (num / 1000).toFixed(0) + 'K';

      return num.toFixed(0);
    }

    const formatMonth = (monthStr) => {
      if (!monthStr) return ''
      const [year, month] = monthStr.split('-')
      const date = new Date(year, month - 1)
      return date.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
    }

    const formatMonthShort = (monthStr) => {
      if (!monthStr) return ''
      const [year, month] = monthStr.split('-')
      const date = new Date(year, month - 1)
      return date.toLocaleDateString('ru-RU', { month: 'short' })
    }

    const getCategoryPercentage = (categoryAmount) => {
      const total = analytics.value.totals?.expenses || 1
      return ((categoryAmount / total) * 100).toFixed(1)
    }

    const getCategoryTrend = (category) => {
      if (!category) return null
      const current = category.total || 0
      const average = category.average_monthly || 0

      if (current > average * 1.2) return 'growth'
      if (current < average * 0.8) return 'decline'
      return 'stable'
    }

    const getTrendLabel = (trend) => {
      switch(trend) {
        case 'growth': return 'Рост ↑'
        case 'decline': return 'Снижение ↓'
        case 'stable': return 'Стабильность →'
        default: return '—'
      }
    }

    const getBudgetStatusLabel = (status) => {
      switch(status) {
        case 'good': return 'В норме'
        case 'warning': return 'Близко к лимиту'
        case 'critical': return 'Превышен'
        case 'no_limit': return 'Без лимита'
        default: return '—'
      }
    }

    const getPriorityLabel = (type) => {
      switch(type) {
        case 'critical': return 'Высокий приоритет'
        case 'warning': return 'Средний приоритет'
        case 'success': return 'Информация'
        default: return ''
      }
    }

    const formatTimeAgo = (index) => {
      const minutesAgo = (index + 1) * 5
      return `${minutesAgo} мин назад`
    }

    const getCategoryColor = (index) => {
      const colors = [
        '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
        '#9b59b6', '#1abc9c', '#d35400', '#34495e'
      ]
      return colors[index % colors.length]
    }

    const getDashArray = (category) => {
      const percentage = getCategoryPercentage(category.total || 0)
      const circumference = 2 * Math.PI * 80
      const dashLength = (percentage / 100) * circumference
      return `${dashLength} ${circumference}`
    }

    const getDashOffset = (category, index) => {
      const categories = analytics.value.category_spending || []
      let totalPercentage = 0
      for (let i = 0; i < index; i++) {
        if (categories[i]) {
          totalPercentage += parseFloat(getCategoryPercentage(categories[i].total || 0))
        }
      }
      const circumference = 2 * Math.PI * 80
      const offset = (totalPercentage / 100) * circumference
      return -offset
    }

    const applyOptimalLimit = async (item) => {
      if (!item?.category_id) {
        alert('Ошибка: ID категории не найден')
        return
      }

      try {
        const response = await axios.put(`/api/categories/${item.category_id}`, {
          budget_limit: item.recommended_limit
        })

        if (response.data.status === 'success') {
          alert(`Лимит для категории "${item.category_name}" установлен в ${formatMoney(item.recommended_limit)}`)
          fetchAnalytics()
        }
      } catch (error) {
        console.error('Error updating category limit:', error)
        alert('Ошибка при обновлении лимита')
      }
    }

    // Инициализация
    onMounted(() => {
      fetchAnalytics()
      window.addEventListener('resize', updateChartDimensions)
    })

    // Наблюдаем за изменением даты
    watch([selectedPeriod, selectedDate], () => {
      if (!loading.value) {
        fetchAnalytics()
      }
    })

    // Очистка
    onUnmounted(() => {
      window.removeEventListener('resize', updateChartDimensions)
    })

    return {
      analytics,
      loading,
      selectedPeriod,
      selectedDate,
      showOptimalDistribution,
      hoveredCategory,
      chartArea,
      tooltip,
      balanceClass,
      financialHealth,
      forecasts,
      expenseCategoriesDistribution,
      weightedData,
      lineChartMonths,
      actualValues,
      weightedValues,
      maxExpenses,
      minExpenses,
      averageExpenses,
      trendChange,
      trendChangePercentage,
      lineChartYTicks,
      actualPoints,
      weightedPoints,
      actualLinePath,
      weightedLinePath,
      chartWidth,
      chartHeight,
      trendDirectionLabel,
      trendDirectionClass,
      cashGapForecast,
      cashGapStatus,
      cashGapMessage,
      fetchAnalytics,
      formatMoney,
      formatNumber,
      formatShortMoney,
      formatMonth,
      formatMonthShort,
      getCategoryPercentage,
      getCategoryTrend,
      getTrendLabel,
      getBudgetStatusLabel,
      getPriorityLabel,
      formatTimeAgo,
      getCategoryColor,
      getDashArray,
      getDashOffset,
      applyOptimalLimit,
      getGridLinePosition,
      getMonthPosition,
      showTooltip,
      hideTooltip
    }
  }
}
</script>

<style scoped>
/* Все стили остаются прежними, кроме следующих добавлений для линейного графика */

/* Контейнер для линейного графика */
.line-chart-container {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 2rem;
  border: 2px solid #e9ecef;
  margin-bottom: 2rem;
}

.line-chart-wrapper {
  position: relative;
  height: 400px;
  display: flex;
}

.line-chart-y-axis {
  width: 60px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding-right: 1rem;
  border-right: 2px solid #e9ecef;
}

.y-tick {
  font-size: 0.8rem;
  color: #7f8c8d;
  text-align: right;
  padding-right: 0.5rem;
}

.line-chart-area {
  flex: 1;
  position: relative;
  padding: 1rem;
  background: white;
  border-radius: 8px;
}

.chart-grid {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

.grid-line {
  position: absolute;
  background: #f0f0f0;
}

.grid-line.horizontal {
  left: 0;
  right: 0;
  height: 1px;
}

.grid-line.vertical {
  top: 0;
  bottom: 0;
  width: 1px;
}

.trend-line-svg {
  position: absolute;
  top: 0;
  left: 0;
}

.data-point {
  cursor: pointer;
  transition: r 0.2s;
}

.data-point:hover {
  r: 8;
}

.weighted-point {
  stroke-dasharray: 2, 2;
}

.x-axis-labels {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 40px;
  display: flex;
  justify-content: space-between;
}

.month-label {
  position: absolute;
  transform: translateX(-50%);
  font-size: 0.8rem;
  color: #7f8c8d;
  text-align: center;
  white-space: nowrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 2px;
}

.legend-color.actual {
  background-color: #e74c3c;
}

.legend-color.weighted {
  background-color: #3498db;
}

.line-chart-tooltip {
  position: absolute;
  background: rgba(44, 62, 80, 0.95);
  color: white;
  padding: 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
  min-width: 200px;
  z-index: 100;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  pointer-events: none;
  backdrop-filter: blur(5px);
}

.tooltip-header {
  margin-bottom: 0.5rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.tooltip-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.tooltip-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tooltip-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.tooltip-dot.actual {
  background-color: #e74c3c;
}

.tooltip-dot.weighted {
  background-color: #3498db;
}

.tooltip-difference {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  font-weight: 600;
}

.tooltip-difference.positive {
  color: #27ae60;
}

/* Вертикальное расположение диаграммы и таблицы */
.analysis-vertical {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.pie-chart-section {
  width: 100%;
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
  margin-bottom: 0;
}

.category-table-section {
  width: 100%;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
}

.pie-chart-container {
  display: flex;
  gap: 2rem;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  margin: 20px 0;
}

.pie-chart {
  flex-shrink: 0;
}

.pie-legend {
  flex: 1;
  min-width: 250px;
  max-width: 400px;
}


.tooltip-difference.negative {
  color: #e74c3c;
}

.trend-summary {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
  margin-top: 1.5rem;
}

.trend-direction {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.trend-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.stat-label {
  font-size: 0.9rem;
  color: #7f8c8d;
}

.stat-value {
  font-weight: 600;
  color: #2c3e50;
}

.stat-value.positive {
  color: #27ae60;
}

.stat-value.negative {
  color: #e74c3c;
}


/* Адаптивность для графика */
@media (max-width: 768px) {
  .line-chart-wrapper {
    flex-direction: column;
    height: 450px;
  }

  .line-chart-y-axis {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    padding-right: 0;
    border-right: none;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    height: 40px;
  }

  .line-chart-area {
    height: 350px;
  }

  .trend-stats {
    grid-template-columns: 1fr;
  }
}

/* Все остальные стили из предыдущей версии остаются без изменений */
.analytics-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

.analytics-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

/* Заголовок */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 2px solid #f0f0f0;
}

.header h1 {
  margin: 0;
  font-size: 2.2rem;
  color: #2c3e50;
  font-weight: 700;
}

.period-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.period-controls select,
.period-controls input {
  padding: 0.75rem 1rem;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 1rem;
  background: white;
  transition: all 0.3s;
}

.period-controls select:focus,
.period-controls input:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.refresh-btn {
  padding: 0.75rem 1.5rem;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s;
  font-weight: 600;
}

.refresh-btn:hover:not(:disabled) {
  background: #2980b9;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Загрузка */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(5px);
}

.spinner {
  width: 60px;
  height: 60px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1.5rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Метрики */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

.metric-card {
  background: white;
  padding: 1.5rem;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  display: flex;
  align-items: center;
  gap: 1.25rem;
  transition: all 0.3s;
  border: 2px solid transparent;
}

.metric-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.health-card {
  border-color: v-bind('financialHealth.color');
}

.income-card {
  border-color: #27ae60;
}

.expense-card {
  border-color: #e74c3c;
}

.savings-card {
  border-color: #f39c12;
}

.accuracy-card {
  border-color: #3498db;
}

.metric-icon {
  font-size: 2.5rem;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  border-radius: 12px;
}

.metric-content {
  flex: 1;
}

.metric-content h3 {
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
  color: #7f8c8d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.metric-value {
  font-size: 1.8rem;
  font-weight: 800;
  margin: 0 0 0.25rem 0;
  color: #2c3e50;
}

.metric-label {
  font-size: 0.85rem;
  color: #95a5a6;
  margin-bottom: 0.75rem;
}

.health-progress,
.savings-progress {
  margin-top: 0.75rem;
}

.progress-bar {
  height: 6px;
  background: #ecf0f1;
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar > div {
  height: 100%;
  border-radius: 3px;
}

.savings-progress .progress-bar {
  position: relative;
}

.progress-markers {
  display: flex;
  justify-content: space-between;
  margin-top: 4px;
}

.marker {
  width: 20px;
  height: 4px;
  border-radius: 2px;
}

.marker.poor {
  background: #e74c3c;
}

.marker.good {
  background: #f39c12;
}

.marker.excellent {
  background: #27ae60;
}

.trend-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #7f8c8d;
}

.trend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.trend-dot.growth {
  background: #e74c3c;
}

.trend-dot.decline {
  background: #27ae60;
}

.trend-dot.stable {
  background: #f39c12;
}

.balance-diff {
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  background: #f8f9fa;
  display: inline-block;
}

.accuracy-scale {
  margin-top: 0.75rem;
}

.scale-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #95a5a6;
  margin-bottom: 4px;
}

.scale-bar {
  height: 6px;
  background: #ecf0f1;
  border-radius: 3px;
  overflow: hidden;
}

.scale-fill {
  height: 100%;
  background: linear-gradient(90deg, #e74c3c, #f39c12, #27ae60);
  border-radius: 3px;
  transition: width 0.5s ease;
}

/* Прогноз кассового разрыва */
.cash-gap-section {
  background: white;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  margin-bottom: 2.5rem;
}

.cash-gap-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.cash-gap-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.cash-gap-card {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 12px;
  text-align: center;
  border: 2px solid #e9ecef;
  transition: all 0.3s;
}

.cash-gap-card:hover {
  border-color: #3498db;
  transform: translateY(-2px);
}

.result-card {
  font-weight: 600;
}

.result-card.critical {
  background: #ffeaea;
  border-color: #e74c3c;
  color: #c0392b;
}

.result-card.warning {
  background: #fff4e6;
  border-color: #f39c12;
  color: #d35400;
}

.result-card.good {
  background: #e8f6ef;
  border-color: #27ae60;
  color: #27ae60;
}

.card-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.card-content h4 {
  margin: 0 0 0.75rem 0;
  font-size: 1rem;
  color: #7f8c8d;
}

.card-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.card-trend {
  font-size: 0.85rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  display: inline-block;
}

.card-trend.growth {
  background: #ffeaea;
  color: #e74c3c;
}

.card-trend.decline {
  background: #e8f6ef;
  color: #27ae60;
}

.card-trend.stable {
  background: #f4f4f4;
  color: #7f8c8d;
}

.cash-gap-status {
  font-size: 0.9rem;
  font-weight: 600;
  margin-top: 0.5rem;
}

/* Оптимальное распределение */
.optimal-distribution {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 2px solid #f0f0f0;
}

.toggle-distribution {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 1rem 2rem;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  margin-bottom: 1.5rem;
}

.toggle-distribution:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.distribution-table {
  background: white;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  overflow: hidden;
}

.distribution-table h3 {
  margin: 0;
  padding: 1.5rem;
  background: #f8f9fa;
  border-bottom: 2px solid #e9ecef;
  font-size: 1.2rem;
  color: #2c3e50;
}

.distribution-table table {
  width: 100%;
  border-collapse: collapse;
}

.distribution-table th {
  padding: 1rem;
  background: #34495e;
  color: white;
  font-weight: 600;
  text-align: left;
  border-bottom: 2px solid #2c3e50;
}

.distribution-table td {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.distribution-table tr:hover {
  background: #f8f9fa;
}

.recommended-limit {
  font-weight: 700;
  color: #27ae60;
}

.stability-indicator {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.stability-bar {
  flex: 1;
  height: 8px;
  background: #ecf0f1;
  border-radius: 4px;
  overflow: hidden;
}

.stability-fill {
  height: 100%;
  background: linear-gradient(90deg, #e74c3c, #f39c12, #27ae60);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.stability-value {
  font-weight: 600;
  color: #2c3e50;
  min-width: 40px;
}

.apply-limit-btn {
  background: #3498db;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.apply-limit-btn:hover {
  background: #2980b9;
  transform: translateY(-1px);
}

/* Тренды */
.trends-section {
  background: white;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  margin-bottom: 2.5rem;
}

.trends-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.chart-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #2c3e50;
}

.chart-period {
  font-size: 0.9rem;
  color: #95a5a6;
}

.y-tick {
  font-size: 0.8rem;
  color: #7f8c8d;
  text-align: right;
  padding-right: 0.5rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #7f8c8d;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
}

.trend-summary {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
  margin-top: 1.5rem;
}

.trend-direction {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: #2c3e50;
}

.trend-direction .trend-up {
  color: #e74c3c;
}

.trend-direction .trend-down {
  color: #27ae60;
}

.trend-direction .trend-stable {
  color: #f39c12;
}

/* Линейный график */
.line-chart-container {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 1.5rem;
  border: 2px solid #e9ecef;
}

.line-chart-y-axis {
  width: 60px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding-right: 1rem;
  border-right: 2px solid #e9ecef;
}

.line-chart-area {
  flex: 1;
  position: relative;
  padding: 1rem;
}

.month-label {
  font-size: 0.75rem;
  fill: #7f8c8d;
}

.line-chart-tooltip {
  position: absolute;
  background: rgba(44, 62, 80, 0.95);
  color: white;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.85rem;
  min-width: 150px;
  z-index: 10;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  pointer-events: none;
}

.largest-transactions-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.transactions-section h2 {
  margin: 0 0 1.25rem 0;
  font-size: 1.3rem;
  color: #2c3e50;
}

/* Подробная статистика регрессии */
.regression-stats-detailed {
  background: white;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  margin-bottom: 2.5rem;
}

.regression-stats-detailed h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.regression-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

.regression-card {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
}

.regression-card h3 {
  margin: 0 0 1rem 0;
  color: #2c3e50;
  font-size: 1.2rem;
}

.regression-equation {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  margin-bottom: 1.5rem;
}

.equation {
  font-family: 'Courier New', monospace;
  font-size: 1.1rem;
  color: #3498db;
  display: block;
  margin-top: 0.5rem;
}

.regression-details {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.regression-detail {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid #3498db;
}

.detail-label {
  font-weight: 600;
  color: #2c3e50;
  display: block;
  margin-bottom: 0.25rem;
}

.detail-value {
  font-size: 1.2rem;
  font-weight: 700;
  color: #2c3e50;
  display: block;
  margin-bottom: 0.25rem;
}

.detail-value.r-squared {
  color: #27ae60;
}

.detail-value.forecast-value {
  color: #e74c3c;
}

.detail-description {
  font-size: 0.85rem;
  color: #7f8c8d;
  line-height: 1.4;
}

.regression-interpretation {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1.5rem;
  border-radius: 12px;
}

.regression-interpretation h4 {
  margin: 0 0 1rem 0;
  color: white;
  font-size: 1.1rem;
}

.interpretation-content {
  font-size: 0.95rem;
  line-height: 1.6;
}

.interpretation-content p {
  margin: 0 0 0.75rem 0;
}

.interpretation-content p:last-child {
  margin-bottom: 0;
}

.recommendations-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.no-rec-content h3 {
  margin: 0 0 0.5rem 0;
  color: #27ae60;
  font-size: 1.3rem;
}

.no-rec-content p {
  margin: 0;
  color: #5d6d7e;
  font-size: 1rem;
}

.recommendation-card.critical .rec-header {
  border-color: #e74c3c;
  background: #ffeaea;
}

.recommendation-card.warning .rec-header {
  border-color: #f39c12;
  background: #fff4e6;
}

.recommendation-card.success .rec-header {
  border-color: #27ae60;
  background: #e8f6ef;
}

.rec-header h4 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.rec-content p {
  margin: 0;
  color: #5d6d7e;
  line-height: 1.6;
}

/* Категории */
.category-analysis {
  background: white;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  margin-bottom: 2.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 2px solid #f0f0f0;
}

.section-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.total-summary {
  font-size: 1.1rem;
  color: #5d6d7e;
}

.total-summary strong {
  color: #2c3e50;
}

.category-count {
  font-size: 0.9rem;
  color: #95a5a6;
  margin-left: 0.5rem;
}

.analysis-container {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
}

.pie-chart-section {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
}

.pie-chart-section h3 {
  margin: 0 0 1.5rem 0;
  font-size: 1.2rem;
  color: #2c3e50;
}

.pie-chart-container {
  display: flex;
  gap: 2rem;
  align-items: center;
}

.pie-chart {
  flex-shrink: 0;
}

.pie-segment {
  cursor: pointer;
  transition: opacity 0.3s;
}

.pie-segment:hover {
  opacity: 0.9;
}

.pie-center-text {
  font-size: 1.5rem;
  font-weight: 700;
  fill: #2c3e50;
}

.pie-center-subtext {
  font-size: 0.8rem;
  fill: #95a5a6;
}

.pie-legend {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
}

.legend-item:hover,
.legend-item.active {
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.legend-text {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.legend-name {
  font-size: 0.9rem;
  color: #2c3e50;
  font-weight: 500;
}

.legend-value {
  font-size: 0.85rem;
  color: #7f8c8d;
  font-weight: 600;
}

.legend-percentage {
  font-size: 0.85rem;
  color: #3498db;
  font-weight: 700;
  min-width: 45px;
  text-align: right;
}

.category-details {
  margin-top: 1.5rem;
  padding: 1.25rem;
  background: white;
  border-radius: 8px;
  border: 2px solid #e9ecef;
}

.category-details h4 {
  margin: 0 0 1rem 0;
  color: #2c3e50;
  font-size: 1.1rem;
}

.category-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stat-label {
  font-size: 0.8rem;
  color: #95a5a6;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 1rem;
  font-weight: 700;
  color: #2c3e50;
}

/* Таблица категорий */
.category-table-section {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  border: 2px solid #e9ecef;
}

.category-table-section h3 {
  margin: 0 0 1.5rem 0;
  font-size: 1.2rem;
  color: #2c3e50;
}

.table-container {
  overflow-x: auto;
}

.categories-table {
  width: 100%;
  border-collapse: collapse;
}

.categories-table th {
  padding: 1rem;
  background: #34495e;
  color: white;
  font-weight: 600;
  text-align: left;
  border-bottom: 2px solid #2c3e50;
  white-space: nowrap;
}

.categories-table td {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.categories-table tr.highlighted {
  background: #f8f9fa;
}

.categories-table tr:hover {
  background: #f0f7ff;
}

.col-category {
  min-width: 180px;
}

.col-amount {
  min-width: 140px;
}

.col-limit {
  min-width: 120px;
}

.col-status {
  min-width: 100px;
}

.col-trend {
  min-width: 100px;
}

.category-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.category-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.category-name {
  font-weight: 500;
  color: #2c3e50;
}

.amount-value {
  font-weight: 700;
  color: #2c3e50;
  font-size: 1.1rem;
}

.amount-average {
  font-size: 0.8rem;
  color: #95a5a6;
  margin-top: 0.25rem;
}

.limit-value {
  font-weight: 600;
  color: #2c3e50;
}

.no-limit {
  color: #95a5a6;
  font-style: italic;
  font-size: 0.9rem;
}

.limit-progress {
  margin-top: 0.5rem;
}

.progress-bar {
  height: 6px;
  background: #ecf0f1;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.progress-fill {
  height: 100%;
  border-radius: 3px;
}

.progress-fill.good {
  background: #27ae60;
}

.progress-fill.warning {
  background: #f39c12;
}

.progress-fill.critical {
  background: #e74c3c;
}

.progress-text {
  font-size: 0.8rem;
  color: #95a5a6;
  font-weight: 600;
}

.status-badge {
  padding: 0.4rem 0.75rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-block;
  text-align: center;
  min-width: 100px;
}

.status-badge.good {
  background: #e8f6ef;
  color: #27ae60;
}

.status-badge.warning {
  background: #fff4e6;
  color: #f39c12;
}

.status-badge.critical {
  background: #ffeaea;
  color: #e74c3c;
}

.status-badge.no_limit {
  background: #f4f4f4;
  color: #7f8c8d;
}

.trend-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #7f8c8d;
}

.trend-arrow {
  font-size: 1.2rem;
}

.trend-arrow.growth {
  color: #e74c3c;
}

.trend-arrow.decline {
  color: #27ae60;
}

.trend-arrow.stable {
  color: #f39c12;
}

.no-trend {
  color: #95a5a6;
  font-style: italic;
}

.info-content h4 {
  margin: 0 0 1rem 0;
  font-size: 1.3rem;
  color: white;
}

.info-content ul {
  margin: 0;
  padding-left: 1.5rem;
}

.info-content li {
  margin-bottom: 0.5rem;
  line-height: 1.5;
}

.info-content li strong {
  color: #f8f9fa;
}

/* Адаптивность */
@media (max-width: 1200px) {
  .analytics-page {
    padding: 1.5rem;
  }

  .metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .regression-container {
    grid-template-columns: 1fr;
  }

}

@media (max-width: 768px) {
  .analytics-page {
    padding: 1rem;
  }

  .header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .period-controls {
    flex-wrap: wrap;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
  }

  .cash-gap-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .recommendations-grid {
    grid-template-columns: 1fr;
  }

  .pie-chart-container {
    flex-direction: column;
  }

  .category-stats {
    grid-template-columns: 1fr;
  }

  .y-tick {
    text-align: center;
    padding-right: 0;
  }

  .line-chart-y-axis {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    padding-right: 0;
    border-right: none;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
  }
}

@media (max-width: 576px) {
  .cash-gap-cards {
    grid-template-columns: 1fr;
  }

  .distribution-table {
    font-size: 0.9rem;
  }

  .distribution-table th,
  .distribution-table td {
    padding: 0.75rem;
  }

  .categories-table th,
  .categories-table td {
    padding: 0.75rem;
  }

  .categories-table {
    font-size: 0.9rem;
  }
}
</style>