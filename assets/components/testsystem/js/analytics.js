/**
 * ANALYTICS & REPORTS - Sprint 15
 * Аналитика и отчеты
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : null;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    async function apiCall(action, data = {}) {
        try {
            const csrfToken = getCsrfToken();
            if (csrfToken) data.csrf_token = csrfToken;

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, data })
            });

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const analyticsContainer = document.getElementById('analytics-container');
        if (analyticsContainer) {
            loadAnalytics();
        }

        document.getElementById('export-report-btn')?.addEventListener('click', exportReport);
        document.getElementById('analytics-period-select')?.addEventListener('change', loadAnalytics);
    });

    async function loadAnalytics() {
        const period = document.getElementById('analytics-period-select')?.value || 'month';

        try {
            const result = await apiCall('getAnalyticsDashboard', { period: period });

            if (result.success) {
                renderAnalytics(result.data);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load analytics error:', error);
            document.getElementById('analytics-container').innerHTML = `
                <div class="alert alert-danger">Ошибка загрузки: ${escapeHtml(error.message)}</div>
            `;
        }
    }

    function renderAnalytics(data) {
        const container = document.getElementById('analytics-container');

        let html = `
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="fs-3 fw-bold text-primary">${data.tests_completed || 0}</div>
                            <div class="text-muted">Тестов завершено</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="fs-3 fw-bold text-success">${data.avg_score || 0}%</div>
                            <div class="text-muted">Средний балл</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="fs-3 fw-bold text-info">${data.active_users || 0}</div>
                            <div class="text-muted">Активных пользователей</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="fs-3 fw-bold text-warning">${data.total_time || 0}ч</div>
                            <div class="text-muted">Времени обучения</div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Популярные тесты</h5>
                        </div>
                        <div class="card-body">
                            ${renderPopularTests(data.popular_tests || [])}
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Когортный анализ</h5>
                        </div>
                        <div class="card-body">
                            ${renderCohortAnalysis(data.cohorts || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    function renderPopularTests(tests) {
        if (!tests || tests.length === 0) {
            return '<p class="text-muted">Нет данных</p>';
        }

        let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Тест</th><th>Прохождений</th><th>Средний балл</th></tr></thead><tbody>';

        tests.forEach(test => {
            html += `
                <tr>
                    <td>${escapeHtml(test.title)}</td>
                    <td>${test.completions}</td>
                    <td><span class="badge bg-success">${test.avg_score}%</span></td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        return html;
    }

    function renderCohortAnalysis(cohorts) {
        if (!cohorts || cohorts.length === 0) {
            return '<p class="text-muted">Нет данных</p>';
        }

        let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Когорта</th><th>Пользователей</th><th>Retention</th></tr></thead><tbody>';

        cohorts.forEach(cohort => {
            html += `
                <tr>
                    <td>${escapeHtml(cohort.name)}</td>
                    <td>${cohort.users}</td>
                    <td><span class="badge bg-info">${cohort.retention}%</span></td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        return html;
    }

    async function exportReport() {
        const format = document.getElementById('export-format')?.value || 'csv';

        try {
            const result = await apiCall('exportAnalyticsReport', { format: format });

            if (result.success && result.file_url) {
                window.location.href = result.file_url;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Export error:', error);
            alert('Ошибка экспорта: ' + error.message);
        }
    }

    window.Analytics = {
        loadAnalytics,
        exportReport
    };

})();
