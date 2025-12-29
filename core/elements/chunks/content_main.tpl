{* Чанк: content_main - Контент главной страницы для siteExtra
   Вызывается из: content.tpl (для resource.id = 1)
   Расширяет: content_default
   Включает: block.gallery
*}
{extends 'content_default'}
{block 'after_content'}
  {include 'block.gallery'}
{/block}