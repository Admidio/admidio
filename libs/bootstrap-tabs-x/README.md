<h1 align="center">
    <a href="http://plugins.krajee.com" title="Krajee Plugins" target="_blank">
        <img src="http://kartik-v.github.io/bootstrap-fileinput-samples/samples/krajee-logo-b.png" alt="Krajee Logo"/>
    </a>
    <br>
    bootstrap-tabs-x
    <hr>
    <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=DTP3NZQ6G2AYU"
       title="Donate via Paypal" target="_blank"><img src="https://kartik-v.github.io/bootstrap-fileinput-samples/samples/donate.png" height="60" alt="Donate"/></a>
    &nbsp; &nbsp; &nbsp;
    <a href="https://www.buymeacoffee.com/kartikv" title="Buy me a coffee" ><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" height="60" alt="kartikv" /></a>
</h1>

<div align="center">

[![Bower version](https://badge.fury.io/bo/bootstrap-tabs-x.svg)](http://badge.fury.io/bo/bootstrap-tabs-x)
[![Latest Stable Version](https://poser.pugx.org/kartik-v/bootstrap-tabs-x/v/stable)](https://packagist.org/packages/kartik-v/bootstrap-tabs-x)
[![License](https://poser.pugx.org/kartik-v/bootstrap-tabs-x/license)](https://packagist.org/packages/kartik-v/bootstrap-tabs-x)
[![Packagist Downloads](https://poser.pugx.org/kartik-v/bootstrap-tabs-x/downloads)](https://packagist.org/packages/kartik-v/bootstrap-tabs-x)
[![Monthly Downloads](https://poser.pugx.org/kartik-v/bootstrap-tabs-x/d/monthly)](https://packagist.org/packages/kartik-v/bootstrap-tabs-x)

</div>

Extended Bootstrap Tabs with ability to align tabs in multiple ways, add borders, rotated titles, load tab content via ajax including caching, and more. This plugin includes various CSS3 styling enhancements
and various tweaks to the core [Bootstrap Tabs plugin](http://getbootstrap.com/javascript/#tabs).

![Bootstrap Tabs X Screenshot](https://lh3.googleusercontent.com/-vWD5-6XoYp4/U9zmysBfbEI/AAAAAAAAALo/-Hkbe-YAB6k/w678-h551-no/bootstrap-tabs-x.jpg)

## Features  

The plugin offers these enhanced features:

- Supports all Bootstrap library releases from v5.x, v4.x and v3.x.
- Supports various tab opening directions: `above` (default), `below`, `right`, and `left`.
- Allows you to box the tab content in a new `bordered` style. This can work with any of the tab directions above.
- Allows you to align the entire tab content to the `left` (default), `center`, or `right` of the parent container/page.
- Automatically align & format heights and widths for bordered tabs for `right` and `left` positions.
- Allows a rotated `sideways` tab header orientation for the `right` and `left` tab directions.
- Auto detect overflowing header labels for `sideways` orientation (with ellipsis styling) and display full label as a title on hover.
- Ability to load tab content via ajax call.
- With release v1.3.0, you can use this like an enhanced jQuery plugin using the function `$fn.tabsX` on the `.tabs-x` parent element.

## Demo

View the [plugin documentation](http://plugins.krajee.com/tabs-x) and [plugin demos](http://plugins.krajee.com/tabs-x/demo) at Krajee JQuery plugins. 

## Pre-requisites  

1. [Bootstrap 5.x or 4.x or 3.x](http://getbootstrap.com/) (Requires bootstrap `tabs.js`)
2. Latest [JQuery](http://jquery.com/)
3. Most browsers supporting CSS3 & JQuery. 

## Installation

### Using Bower
You can use the `bower` package manager to install. Run:

    bower install bootstrap-tabs-x

### Using Composer
You can use the `composer` package manager to install. Either run:

    $ php composer.phar require kartik-v/bootstrap-tabs-x "@dev"

or add:

    "kartik-v/bootstrap-tabs-x": "@dev"

to your composer.json file

### Manual Install

You can also manually install the plugin easily to your project. Just download the source [ZIP](https://github.com/kartik-v/bootstrap-tabs-x/zipball/master) or [TAR ball](https://github.com/kartik-v/bootstrap-tabs-x/tarball/master) and extract the plugin assets (css and js folders) into your project.

## Usage

### Load Client Assets

You must first load the following assets in your header. 

```html
<!-- bootstrap 5.x or 4.x or 3.x is supported  -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" crossorigin="anonymous">
<!-- if using with bootstrap 5.x and 4.x include bootstrap-tabs-x-bs4.css style.
     If using with bootstrap 3.x include bootstrap-tabs-x.css -->
<link href="https://cdn.jsdelivr.net/gh/kartik-v/bootstrap-tabs-x@1.3.5/css/bootstrap-tabs-x-bs4.min.css" media="all" rel="stylesheet" type="text/css" /&gt;
<!-- alternatively if using with bootstrap 3.x uncomment below and comment out (exclude /bootstrap-tabs-x-bs4.css) -->
<!-- link href="https://cdn.jsdelivr.net/gh/kartik-v/bootstrap-tabs-x@1.3.5/css/bootstrap-tabs-x.css" media="all" rel="stylesheet" type="text/css" / -->
<!-- jquery JS library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- bootstrap.bundle.min.js below is needed if you wish to zoom and preview file content in a detail modal
    dialog. bootstrap 5.x or 4.x is supported. You can also use the bootstrap js 3.3.x versions. -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<!-- bootstrap-tabs-x JS library -->
<script src="https://cdn.jsdelivr.net/gh/kartik-v/bootstrap-tabs-x@1.3.5/js/bootstrap-tabs-x.min.js" type="text/javascript"></script>
```

If you noticed, you need to load the `bootstrap.min.css`, `jquery.min.js`, and `bootstrap.min.js` in addition to the `bootstrap-tabs-x.css` and `bootstrap-tabs-x.js` for
the plugin to work with default settings. 

> Note: The plugin extends the **bootstrap tabs plugin** and hence the `bootstrap.min.js` must be loaded before `bootstrap-tabs-x.js`.

### Markup

You just need to setup the markup for the extended bootstrap tabs to work now. Refer documentation for details.

#### Bootstrap 5.x

```html
<!-- BOOTSTRAP 5 MARKUP -->
<legend>Tabs Above Centered (Bordered)</legend>
<div class="tabs-x align-center tabs-above tab-bordered">
    <!-- tabs -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="home-tab" data-bs-toggle="tab" href="#home" role="tab" aria-controls="home" aria-expanded="true">Home</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="profile-tab" data-bs-toggle="tab" href="#profile" role="tab" aria-controls="profile">Profile</a>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                Dropdown
            </a>
            <div class="dropdown-menu">
                <a class="dropdown-item" id="dropdown1-tab" href="#dropdown1" role="tab" data-bs-toggle="tab" aria-controls="dropdown1">Dropdown 1</a>
                <a class="dropdown-item" id="dropdown2-tab" href="#dropdown2" role="tab" data-bs-toggle="tab" aria-controls="dropdown2">Dropdown 2</a>
            </div>
        </li>
    </ul>
    <!-- tab panes -->
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">...</div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">...</div>
        <div class="tab-pane fade" id="dropdown1" role="tabpanel" aria-labelledby="dropdown1-tab">...</div>
        <div class="tab-pane fade" id="dropdown2" role="tabpanel" aria-labelledby="dropdown2-tab">...</div>
    </div>
</div>
```

#### Bootstrap 4.x

```html
<!-- BOOTSTRAP 4 MARKUP -->
<legend>Tabs Above Centered (Bordered)</legend>
<div class="tabs-x align-center tabs-above tab-bordered">
  <!-- tabs -->
  <ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-expanded="true">Home</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile">Profile</a>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
        Dropdown
      </a>
      <div class="dropdown-menu">
        <a class="dropdown-item" id="dropdown1-tab" href="#dropdown1" role="tab" data-toggle="tab" aria-controls="dropdown1">Dropdown 1</a>
        <a class="dropdown-item" id="dropdown2-tab" href="#dropdown2" role="tab" data-toggle="tab" aria-controls="dropdown2">Dropdown 2</a>
      </div>
    </li>
  </ul>
  <!-- tab panes -->
  <div class="tab-content" id="myTabContent">
    <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">...</div>
    <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">...</div>
    <div class="tab-pane fade" id="dropdown1" role="tabpanel" aria-labelledby="dropdown1-tab">...</div>
    <div class="tab-pane fade" id="dropdown2" role="tabpanel" aria-labelledby="dropdown2-tab">...</div>
  </div>
</div>
```

#### Bootstrap 3.x

```html
<!-- BOOTSTRAP 3 MARKUP -->
<legend>Tabs Above Centered (Bordered)</legend>
<div class="tabs-x align-center tabs-above tab-bordered">
  <!-- Nav tabs -->
  <ul class="nav nav-tabs" id="myTab" role="tablist">
    <li role="presentation" class="active"><a href="#home" aria-controls="home" role="tab" data-toggle="tab">Home</a></li>
    <li role="presentation"><a href="#profile" aria-controls="profile" role="tab" data-toggle="tab">Profile</a></li>
    <li role="presentation" class="dropdown">
      <a href="#" class="dropdown-toggle" id="myTabDrop1" data-toggle="dropdown" aria-controls="myTabDrop1-contents">
        Dropdown <span class="caret"></span>
      </a>
      <ul class="dropdown-menu" aria-labelledby="myTabDrop1" id="myTabDrop1-contents">
        <li><a href="#dropdown1" role="tab" id="dropdown1-tab" data-toggle="tab" aria-controls="dropdown1">Dropdown 1</a></li>
        <li><a href="#dropdown2" role="tab" id="dropdown2-tab" data-toggle="tab" aria-controls="dropdown2">Dropdown 1</a></li>
      </ul>
    </li>
  </ul>

  <!-- Tab panes -->
  <div class="tab-content">
    <div role="tabpanel" class="tab-pane fade in active" id="home">...</div>
    <div role="tabpanel" class="tab-pane" id="profile">...</div>
    <div role="tabpanel" class="tab-pane" id="settings">...</div>
    <div class="tab-pane fade" id="dropdown1" role="tabpanel" aria-labelledby="dropdown1-tab">...</div>
    <div class="tab-pane fade" id="dropdown2" role="tabpanel" aria-labelledby="dropdown2-tab">...</div>
  </div>
</div>
```

### Via Javascript

The javascript methods and events available in the core bootstrap tabs plugin will be available here as well. Note as in the earlier markup methods, the `tabsX` plugin function behavior is auto-initialized if you set the CSS class `tabs-x` on a container element on the page (e.g. `div`). You can optionally enable tabsX behavior manually via javascript, by not assigning the `tabsX` class on your container. For example if your markup is like below:

```html
<div id="tabs-container" class="align-center">
    <ul class="nav nav-tabs">
        ...
    </ul>
    <div class="tab-content">
        ...
    </div>
</div>
```

You can enable the tabsX plugin via javascript like below:

```js
$("#tabs-container").tabsX({
    enableCache: true,
    maxTitleLength: 10
});
```


## Documentation

View the [plugin documentation at Krajee plugins](http://plugins.krajee.com/tabs-x).

## License

**bootstrap-tabs-x** is released under the BSD 3-Clause License. See the bundled `LICENSE.md` for details.