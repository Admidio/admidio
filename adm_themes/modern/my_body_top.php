
<!-- Here you can add your html code. This code will be applied at the beginning of the <body> area
     of an Admidio module page.
-->

<div id="page" class="container-fluid">
    <div id="header-block" class="admidio-container">
        <a href="<?php echo $gHomepage; ?>">
            <img id="admidio-logo" class="hidden-xs" src="<?php echo THEME_URL; ?>/images/logo.png" alt="Logo" />
        </a>
        <div id="page-h1-membership" class="hidden-xs"><?php echo $gL10n->get('SYS_ONLINE_MEMBERSHIP_ADMINISTRATION'); ?></div>
        <div id="page-h1-orga"><?php echo $gCurrentOrganization->getValue('org_longname'); ?></div>
    </div>

    <div class="row">
        <div class="col-md-9">
            <div id="left-block" class="admidio-container">
