{extends file="layout.tpl"}
{block name='body:id'}pages{/block}
{block name="title"}{if $pages.seo.title}{$pages.seo.title}{else}{$pages.title}{/if}{/block}
{block name="description"}{if $pages.seo.description}{$pages.seo.description}{elseif !empty($pages.resume)}{$pages.resume}{elseif !empty($pages.content)}{$pages.content|strip_tags|truncate:100:'...'}{/if}{/block}
{block name='article'}
    {strip}
    {switch $mollie.status_h}
    {case 'paid' break}
        {$msg = {#status_accept_msg#}}
        {$type = 'success'}
        {capture name="icon"}check{/capture}
    {case 'failed' break}
        {$msg = {#status_decline_msg#}}
        {$type = 'warning'}
        {capture name="icon"}error_outline{/capture}
    {case 'canceled' break}
        {$msg = {#status_cancel_msg#}}
        {$type = 'warning'}
        {capture name="icon"}close{/capture}
    {case 'expired' break}
        {$msg = {#status_expired_msg#}}
        {$type = 'warning'}
        {capture name="icon"}error_outline{/capture}
    {/switch}
    {/strip}
    <div class="container">
        <p class="col-sm-12 alert alert-{$type} fade in">
            <i class="material-icons ico ico-{$smarty.capture.icon}"></i> {$msg}
        </p>
    </div>
{/block}