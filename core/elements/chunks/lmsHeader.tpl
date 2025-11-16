{* LMS Header Chunk - Шапка для страниц LMS *}
{if $_modx->user.id > 0}
<div class="alert alert-info bg-light border-start border-5 border-primary mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-circle me-3" style="font-size: 2.5rem;"></i>
        <div>
            <h5 class="mb-0">Добро пожаловать, {$_modx->user.username}!</h5>
            <small class="text-muted">Продолжайте обучение и развивайте свои навыки</small>
        </div>
    </div>
</div>
{/if}
