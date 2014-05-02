<div class='div_devices'>

<div class='div_devices_border'>
	<table class="table table_devices">
	{foreach from=$data.rows item=i}
		{if $i.state}{$my_val="<img src='{$p.urls.static}/global/img/icon48_{$i.state}.png'><div class='mini'>{$i.value}</class>"}{else}{$my_val=$i.value}{/if}
		<tr class="tr_class_{$i.class} tr_type_{$i.type} tr_state_{$i.state}">
			<td class="td_img">		<img src='{$p.urls.static}{$i.img_url}' class='{if $c.debug.show}jsPopover{/if}' title="{$i.name}" data-content="<pre>{print_r($i,true)}</pre>"></td>
			<td class="td_name">	{$i.name}</td>
			<td class="td_value">	<a href='#' title="{$i.value}">{$my_val}</a></td>
			<td class="td_unit">	{$i.unit|default:$p.units.{$i.type}}</td>
{if $p.code=='devices' or $p.code=='sensors'}
			<td class="td_type">	{$lg.types.{$i.type}|default:"<u>{$i.type}</u>"}<br><i>{$lg.classes.{$i.class}|ucwords}</i></td>
{/if}
{if $p.code=='devices'}
			<td class="td_id">		{$i.uid}<br>({$i.address})</td>
{elseif $p.code=='commands'}
<!--
TODO : Implement Buttons ON, OFF
			<td class="td_value">	<a href='#' class="btn btn-default">ON</a> <a href='#' class="btn btn-default">OFF</a></td> 
-->
{/if}
		</tr>
	{/foreach}
	</table>
</div>
</div>