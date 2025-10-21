<footer>
    <hr>
	<div class="row">
		<div class="col-xs-6">
			<p>&copy;
			  {var $year = '' | date : 'Y'}
			  {if $year == 2025}2025{else}2025—{$year}{/if}
			  {$_modx->config.site_name}
			</p>
		</div>
		<div class="col-xs-6">
			<p class="text-right">
			  <small><a href="https://ilyaut.ru/">Илья Уткин</a></small>
			</p>
		</div>
	</div>
</footer>
