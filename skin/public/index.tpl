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
    {case 'failed' break}
        {$msg = {#status_decline_msg#}}
        {$type = 'warning'}
    {case 'canceled' break}
        {$msg = {#status_cancel_msg#}}
        {$type = 'warning'}
    {case 'expired' break}
        {$msg = {#status_expired_msg#}}
        {$type = 'warning'}
    {/switch}
    {/strip}
    <div class="container">
        <p class="alert alert-{$type}">
            {$msg}
        </p>
    </div>
{/block}