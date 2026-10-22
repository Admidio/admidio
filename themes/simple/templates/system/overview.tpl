<div class="row mb-5">
    {foreach $overviewWidgets as $widget}
        <div class="admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3" id="admidio-plugin-{$widget.id}">
            <div class="card admidio-card">
                <div class="card-body">
                    {$widget.html nofilter}
                </div>
            </div>
        </div>
    {/foreach}
</div>
