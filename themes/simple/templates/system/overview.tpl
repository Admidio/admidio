
<div class="row mb-5">
    {foreach $overviewPlugins as $plugin}
        <div class="admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3" id="admidio-plugin-{$plugin.id}">
            <div class="card admidio-card">
                <div class="card-body">
                    {load_admidio_plugin plugin="{$plugin.name}" file="{$plugin.file}"}
                </div>
            </div>
        </div>
    {/foreach}
</div>
