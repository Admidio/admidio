
<td style="background-color:     #6CA7A8;
           width:                160px;
           border-right-width:   4px;
           border-right-color:   #555555;
           vertical-align:       top;
           text-align:           left;
           padding-right:        5px;">
    <p><a class="menu" href="index.php?download/plugins/plugins.php">Plugin-&Uuml;bersicht</a></p>
    <p><a class="menu" href="index.php?download/plugins/sidebar_announcements.php">Sidebar Announcements</a></p>
    <p><a class="menu" href="index.php?download/plugins/sidebar_dates.php">Sidebar Dates</a></p>
    <?php 
    if(strpos($_SERVER['HTTP_HOST'], "localhost") === false
    && strpos($_SERVER['HTTP_HOST'], "127.0.0.1") === false)
    {
        echo '    
        <p><br /><br />
            <script type="text/javascript"><!--
            google_ad_client = "pub-9192132534802138";
            google_ad_width = 120;
            google_ad_height = 240;
            google_ad_format = "120x240_as";
            google_ad_type = "text";
            google_ad_channel ="3895673277";
            google_color_border = "6CA7A8";
            google_color_bg = "6CA7A8";
            google_color_link = "DDDDDD";
            google_color_url = "DDDDDD";
            google_color_text = "444444";
            //--></script>
            <script type="text/javascript"
              src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
            </script>   
        </p>';
    }
    ?>
</td>
<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Plugins f&uuml;r Admidio!</h2>
   <p>Die folgenden Plugins stehen Ihnen f&uuml;r Admidio zur Verf&uuml;gung. Bitte beachten Sie 
   die jeweiligen Angaben zur kompatiblen Admidio-Version !</p>

   <p><a href="index.php?download/plugins/sidebar_announcements.php">Sidebar Announcements</a><br />
   Die letzten x Ankündigungen können hier aufgelistet werden. Dieses Plugin benötigt sehr wenig Platz und 
   eignet sich für eine Seitenleiste auf der Homepage.</p>

   <p><a href="index.php?download/plugins/sidebar_dates.php">Sidebar Dates</a><br />
   Die kommenden x Termine können mit Hilfe dieses Plugins aufgelistet werden. Es benötigt sehr wenig Platz und 
   eignet sich für eine Seitenleiste auf der Homepage.</p>
</td>