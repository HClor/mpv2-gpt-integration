/**
 * CERTIFICATES SYSTEM - Sprint 16
 * Сертификаты и верификация
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
        const certificatesContainer = document.getElementById('certificates-container');
        if (certificatesContainer) {
            loadCertificates();
        }

        const verifyContainer = document.getElementById('certificate-verify-container');
        if (verifyContainer) {
            initVerification();
        }
    });

    async function loadCertificates() {
        try {
            const result = await apiCall('getMyCertificates', {});

            if (result.success) {
                renderCertificates(result.data || []);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load certificates error:', error);
            document.getElementById('certificates-container').innerHTML = `
                <div class="alert alert-danger">Ошибка загрузки: ${escapeHtml(error.message)}</div>
            `;
        }
    }

    function renderCertificates(certificates) {
        const container = document.getElementById('certificates-container');

        if (certificates.length === 0) {
            container.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-award fs-1 text-muted d-block mb-3"></i>
                    <h5>Нет сертификатов</h5>
                    <p class="text-muted">Завершите траектории обучения или тесты для получения сертификатов</p>
                </div>
            `;
            return;
        }

        let html = '<div class="row g-4">';

        certificates.forEach(cert => {
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-award-fill text-warning fs-1 mb-3"></i>
                            <h5 class="card-title">${escapeHtml(cert.title)}</h5>
                            <p class="text-muted small">${escapeHtml(cert.description || '')}</p>

                            <div class="mb-3">
                                <span class="badge bg-success">Выдан: ${new Date(cert.issued_at).toLocaleDateString('ru-RU')}</span>
                            </div>

                            <p class="small text-muted">Номер сертификата:<br><code>${cert.certificate_number}</code></p>

                            <div class="d-grid gap-2">
                                <a href="/certificate/${cert.certificate_number}"
                                   class="btn btn-primary"
                                   target="_blank">
                                    <i class="bi bi-eye"></i> Просмотреть
                                </a>
                                <button class="btn btn-outline-secondary"
                                        onclick="Certificates.downloadCertificate('${cert.certificate_number}')">
                                    <i class="bi bi-download"></i> Скачать PDF
                                </button>
                                <button class="btn btn-outline-info"
                                        onclick="Certificates.shareCertificate('${cert.certificate_number}')">
                                    <i class="bi bi-share"></i> Поделиться
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    async function downloadCertificate(certificateNumber) {
        try {
            const result = await apiCall('generateCertificatePDF', {
                certificate_number: certificateNumber
            });

            if (result.success && result.pdf_url) {
                window.location.href = result.pdf_url;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Download error:', error);
            alert('Ошибка загрузки: ' + error.message);
        }
    }

    function shareCertificate(certificateNumber) {
        const url = `${window.location.origin}/certificate/${certificateNumber}`;

        if (navigator.share) {
            navigator.share({
                title: 'Мой сертификат',
                text: 'Посмотрите на мой сертификат!',
                url: url
            }).catch(err => console.error('Share error:', err));
        } else {
            // Копируем ссылку в буфер обмена
            navigator.clipboard.writeText(url).then(() => {
                alert('Ссылка скопирована в буфер обмена!');
            });
        }
    }

    function initVerification() {
        document.getElementById('verify-certificate-btn')?.addEventListener('click', verifyCertificate);
    }

    async function verifyCertificate() {
        const certificateNumber = document.getElementById('certificate-number-input')?.value.trim();

        if (!certificateNumber) {
            alert('Введите номер сертификата');
            return;
        }

        const resultContainer = document.getElementById('verification-result');
        resultContainer.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';

        try {
            const result = await apiCall('verifyCertificate', {
                certificate_number: certificateNumber
            });

            if (result.success && result.certificate) {
                const cert = result.certificate;

                resultContainer.innerHTML = `
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle-fill"></i> Сертификат действителен</h5>
                        <hr>
                        <p><strong>Владелец:</strong> ${escapeHtml(cert.owner_name)}</p>
                        <p><strong>Название:</strong> ${escapeHtml(cert.title)}</p>
                        <p><strong>Выдан:</strong> ${new Date(cert.issued_at).toLocaleDateString('ru-RU')}</p>
                        <p><strong>Статус:</strong> ${cert.is_revoked ? '<span class="badge bg-danger">Отозван</span>' : '<span class="badge bg-success">Действителен</span>'}</p>
                    </div>
                `;
            } else {
                resultContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle-fill"></i> Сертификат не найден или недействителен
                    </div>
                `;
            }
        } catch (error) {
            console.error('Verification error:', error);
            resultContainer.innerHTML = `
                <div class="alert alert-danger">
                    Ошибка проверки: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    window.Certificates = {
        loadCertificates,
        downloadCertificate,
        shareCertificate,
        verifyCertificate
    };

})();
