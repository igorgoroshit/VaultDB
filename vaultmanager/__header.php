<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="description" content="">
  <meta name="author" content="">
  <link rel="shortcut icon" href="images/favicon.png" type="image/png">

  <title>VaultDB - Vault Manager</title>

  <link href="css/style.default.css" rel="stylesheet">

  <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!--[if lt IE 9]>
  <script src="js/html5shiv.js"></script>
  <script src="js/respond.min.js"></script>
  <![endif]-->
</head>

<body>
<!-- Preloader -->
<div id="preloader">
    <div id="status"><i class="fa fa-spinner fa-spin"></i></div>
</div>

<section>
  
  <div class="leftpanel">
    
    <div class="logopanel">
        <h1><span>[</span> Vault Manager <span>]</span></h1>
    </div><!-- logopanel -->
        
    <div class="leftpanelinner">    
       
      <h5 class="sidebartitle">Navigation</h5>
      <ul class="nav nav-pills nav-stacked nav-bracket">
        <li <?php if ($page=="home") { echo ' class="active" '; } ?>><a href="?page=home"><i class="fa fa-home"></i> <span>Documentation</span></a></li>
        <li <?php if ($page=="users") { echo ' class="active" '; } ?>><a href="?page=users"><i class="fa fa-user"></i> <span>Users</span></a></li>
        <li <?php if ($page=="groups") { echo ' class="active" '; } ?>><a href="?page=groups"><i class="fa fa-users"></i> <span>Groups</span></a></li>
        <li <?php if ($page=="vaults") { echo ' class="active" '; } ?>><a href="?page=vaults"><i class="fa fa-folder-open"></i> <span>Vaults</span></a></li>
        <li <?php if ($page=="documents") { echo ' class="active" '; } ?>><a href="?page=documents"><i class="fa fa-lock"></i> <span>Documents</span></a></li>
  
      </ul>
      
      <div class="infosummary">
        <h5 class="sidebartitle">Information Summary</h5>    
        <ul>
            <li>
                <div class="datainfo">
                    <span class="text-muted">vaults</span>
                    <h4>630, 201</h4>
                </div>
            </li>
            <li>
                <div class="datainfo">
                    <span class="text-muted">documents</span>
                    <h4>1, 332, 801</h4>
                </div>
            </li>
            <li>
                <div class="datainfo">
                    <span class="text-muted">users</span>
                    <h4>82.2%</h4>
                </div>
            </li>
            <li>
                <div class="datainfo">
                    <span class="text-muted">groups</span>
                    <h4>140.05 - 32</h4>
                </div>
            </li>
        </ul>
      </div><!-- infosummary -->
      
    </div><!-- leftpanelinner -->
  </div><!-- leftpanel -->
  
  <div class="mainpanel">
    
    <div class="headerbar">
      
      <a class="menutoggle"><i class="fa fa-bars"></i></a>
      
    </div><!-- headerbar -->
    
 