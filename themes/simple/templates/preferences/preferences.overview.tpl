<style>
    .admidio-overview-plugin {
        display:flex;
        padding:.5rem;
    }
    .admidio-overview-plugin .card {
        width:100%;
        cursor:move;
    }

    .sortable-placeholder {
        display:flex;
        padding:.5rem;
    }
    .sortable-placeholder > .card{
        width:100%;
        border:2px dashed var(--bs-border-color);
        border-radius:var(--bs-border-radius-lg);
        background:var(--bs-body-bg);
    }
</style>
<script>
    var $container = $("#overview-sortable");
    var uniformHeight = 0;

    // Write the sequence numbers to the hidden inputs
    function writeSequence() {
        $container.find(".admidio-overview-plugin").each(function (index) {
            var key = $(this).data("seq-key");
            if (key) {
                $("input[name='"+key+"']").val(index + 1);
            }
        });
    }
    // Enable/disable button states (first can't move up, last can't move down)
    function updateMoveButtons() {
        var $items = $container.find(".admidio-overview-plugin");
        $items.each(function (i) {
            var $item = $(this);
            var $up   = $item.find('.admidio-plugin-move[data-direction="UP"]');
            var $down = $item.find('.admidio-plugin-move[data-direction="DOWN"]');
            var isFirst = (i === 0);
            var isLast  = (i === $items.length - 1);
            if (isFirst) {
                $up.css("visibility", "hidden");
            } else {
                $up.css("visibility", "visible");
            }
            if (isLast) {
                $down.css("visibility", "hidden");
            } else {
                $down.css("visibility", "visible");
            }
        });
    }

    // Click handler for the arrows
    $(document).on('click', '.admidio-plugin-move', function (e) {
        e.preventDefault();
        var $button = $(this);

        // Find the column for this button
        var $item = $('.admidio-overview-plugin[data-plugin-id="'+ $button.data('plugin-id') +'"]');
        if (!$item.length) {
            return;
        }

        var direction = String($button.data('direction') || '').toUpperCase();
        if (direction === 'UP') {
            var $prevItem = $item.prevAll('.admidio-overview-plugin').first();
            if ($prevItem.length) {
                $item.insertBefore($prevItem);
            }
        } else if (direction === 'DOWN') {
            var $nextItem = $item.nextAll('.admidio-overview-plugin').first();
            if ($nextItem.length) {
                $item.insertAfter($nextItem);
            }
        }

        // Update the sequence numbers after moving
        writeSequence();
        // update the visibility of the move buttons
        updateMoveButtons();
    });

    // Set every card to the tallest card’s height
    function equalizeAllHeights() {
        var $cards = $container.find(".admidio-overview-plugin .card");
        $cards.css("height", "auto");
        uniformHeight = 0;
        $cards.each(function () {
            var height = $(this).outerHeight(true);
            if (height > uniformHeight) {
                uniformHeight = height;
            }
        });
        if (uniformHeight > 0) {
            $cards.height(uniformHeight);
        }
    }

    if ($container.length) {
        $container.sortable({
            items: ".admidio-overview-plugin",
            handle: ".card",
            placeholder: "sortable-placeholder admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3",
            helper: "clone",
            tolerance: "pointer",
            distance: 5,
            start: function (e, ui) {
                // make placeholder a full-size ghost card using the uniform height
                ui.placeholder.html('<div class="card"></div>');
                ui.placeholder.css({ height: uniformHeight, minHeight: uniformHeight, width: "" });
                ui.placeholder.children(".card").css({ height: uniformHeight });
            },
            change: function (e, ui) {
                // keep using the uniform height as you cross rows
                ui.placeholder.css({ height: uniformHeight, minHeight: uniformHeight, width: "" });
            },
            update: function () {
                writeSequence();
            }
        });

        // initial
        writeSequence();
        equalizeAllHeights();
    }
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {* include input elements for all $elements ending with _overview_sequence *}
    {foreach $elements as $key => $element}
        {if {string_contains haystack=$key needle="_overview_sequence"}}
            {include 'sys-template-parts/form.input.tpl' data=$element}
        {/if}
    {/foreach}

    {* show the description *}
    {include 'sys-template-parts/form.description.tpl' data=$elements['adm_overview_description']}

    {* Display the overview plugins container *}
    <div class="row mb-5 admidio-sortable" id="overview-sortable" style="background-color: white;">
    {foreach $overviewPlugins as $overviewPlugin}
        <div class="admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3" data-seq-key="{$overviewPlugin.sequence.key}" data-plugin-id="{$overviewPlugin.id}">
            <div class="card admidio-card" style="margin: 0">
                <div class="d-block d-md-none">
                    <div class="card-header">
                        {if isset($overviewPlugin.icon)}<i class="bi {$overviewPlugin.icon}"></i>{/if} {$overviewPlugin.name}
                        {if !$overviewPlugin.enabled}
                            <span class="badge bg-secondary">{$l10n->get('SYS_DISABLED')}</span>
                        {/if}
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="d-none d-md-block">
                        <div class="text-center">
                            {if isset($overviewPlugin.icon)}<i class="bi {$overviewPlugin.icon}"></i>{/if} {$overviewPlugin.name}
                            {if !$overviewPlugin.enabled}
                                <span class="badge bg-secondary">{$l10n->get('SYS_DISABLED')}</span>
                            {/if}
                        </div>
                    </div>
                    <div class="d-block d-md-none">
                        <a class="admidio-plugin-move" href="javascript:void(0)" data-plugin-id="{$overviewPlugin.id}"
                           data-direction="UP">
                            <i class="bi bi-arrow-up-circle-fill"></i>{$l10n->get('SYS_MOVE_UP', array('SYS_EXTENSION'))}</a>
                        <br>
                        <a class="admidio-plugin-move" href="javascript:void(0)" data-plugin-id="{$overviewPlugin.id}"
                           data-direction="DOWN">
                            <i class="bi bi-arrow-down-circle-fill"></i>{$l10n->get('SYS_MOVE_DOWN', array('SYS_EXTENSION'))}</a>
                    </div>
                </div>
            </div>
        </div>
    {/foreach}
    </div>

    {* Include the save button *}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_overview']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>