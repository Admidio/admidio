
<!-- Here you can add your html code. This code will be applied at the beginning of the <body> area
     of an Admidio module page.
-->

<div id="page">
    
    <div id="header-block" class="admFrame">
        <img id="adm-logo" src="<?php echo THEME_PATH; ?>/images/logo.png" alt="Logo" />
        <h1 id="page-h1-membership"><?php echo $gL10n->get('SYS_ONLINE_MEMBERSHIP_ADMINISTRATION'); ?></h1>
        <h1 id="page-h1-orga"><?php echo $gCurrentOrganization->getValue('org_longname'); ?></h1>
    </div>

	<div id="left-block" class="admFrame">
