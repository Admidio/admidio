
<!-- Here you can add your html code. This code will be applied at the end of the <body> area
     and after the Admidio module code.
-->

            </div><!-- closes "div#left-block" -->
        </div><!-- closes "div.col-md-9" -->
        <div class="col-md-3">
            <div id="right-block" class="admidio-container">
                <?php

                require(ADMIDIO_PATH . FOLDER_PLUGINS . '/login_form/login_form.php');

                // create html page object and display Menu
                $page = new HtmlPage();
                echo $page->showMainMenu(false);

                ?>
            </div><!-- closes "div#right-block" -->
        </div><!-- closes "div.col-md-3" -->
    </div><!-- closes "div.row" -->
</div><!-- closes "div#page" -->


<p id="copyright">
    <a href="<?php echo ADMIDIO_HOMEPAGE; ?>" style="text-decoration: none;">
        <img src="<?php echo THEME_URL; ?>/images/admidio_writing_100.png"
             alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             style="border: 0; vertical-align: bottom;" />
    </a><br />
    <span style="font-size: 9pt; padding-left: 8px;">&copy; 2004 - 2018&nbsp;&nbsp;
        <?php echo $gL10n->get('SYS_ADMIDIO_TEAM'). '<br />';
            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0)
            {
                echo '<a href="'.$gSettingsManager->getString('system_url_data_protection').'">'.$gL10n->get('SYS_DATA_PROTECTION').'</a>';
            }
            if ($gSettingsManager->has('system_url_imprint') && strlen($gSettingsManager->getString('system_url_imprint')) > 0)
            {
                echo '&nbsp;&nbsp;&nbsp;<a href="'.$gSettingsManager->getString('system_url_imprint').'">'.$gL10n->get('SYS_IMPRINT').'</a>';
            }
        ?>
    </span>
</p>
