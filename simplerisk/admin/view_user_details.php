<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// Default is no alert
	$alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// If the user has been updated
	if (isset($_POST['update_user']) && isset($_POST['user']))
	{
	        // Get the user ID
        	$user_id = (int)$_POST['user'];

		// Verify the user ID value is an integer
		if (is_int($user_id))
		{
			// Get the submitted values
			$name = $_POST['name'];
			$email = $_POST['email'];
			$teams = isset($_POST['team']) ? $_POST['team'] : array('none');
			$language = get_name_by_value("languages", (int)$_POST['languages']);
			$asset = isset($_POST['asset']) ? '1' : '0';
	                $admin = isset($_POST['admin']) ? '1' : '0';
        	        $submit_risks = isset($_POST['submit_risks']) ? '1' : '0';
                	$modify_risks = isset($_POST['modify_risks']) ? '1' : '0';
			$close_risks = isset($_POST['close_risks']) ? '1' : '0';
                	$plan_mitigations = isset($_POST['plan_mitigations']) ? '1' : '0';
			$review_veryhigh = isset($_POST['review_veryhigh']) ? '1' : '0';
                	$review_high = isset($_POST['review_high']) ? '1' : '0';
                	$review_medium = isset($_POST['review_medium']) ? '1' : '0';
                	$review_low = isset($_POST['review_low']) ? '1' : '0';
			$review_insignificant = isset($_POST['review_insignificant']) ? '1' : '0';
			$multi_factor = (int)$_POST['multi_factor'];

                        // Create a boolean for all
                        $all = false;

                        // Create a boolean for none
                        $none = false;

			// Set the team to empty to start
			$team = "";

                        // Create the team value
                        foreach ($teams as $value)
                        {
                                // If the selected value is all
                                if ($value == "all") $all = true;

                                // If the selected value is none
                                if ($value == "none") $none = true;

                                $team .= ":";
                                $team .= $value;
                                $team .= ":";
                        }

                        // If all was selected then assign all teams
                        if ($all) $team = "all";

                        // If none was selected then assign no teams
                        if ($none) $team = "none";

			// Update the user
			update_user($user_id, $name, $email, $team, $language, $asset, $admin, $review_veryhigh, $review_high, $review_medium, $review_low, $review_insignificant, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor);

			$alert = "good";
                        $alert_message = "The user was updated successfully.";
		}
	}

        // Check if a userid was sent
        if (isset($_POST['user']))
        {
	        // Get the user ID
	        $user_id = (int)$_POST['user'];

                // Get the users information
                $user_info = get_user_by_id($user_id);
		$type = $user_info['type'];
                $username = $user_info['username'];
                $name = $user_info['name'];
                $email = $user_info['email'];
                $last_login = $user_info['last_login'];
		$language = $user_info['lang'];
		$teams = $user_info['teams'];
                $admin = $user_info['admin'];
		$asset = $user_info['asset'];
		$review_veryhigh = $user_info['review_veryhigh'];
                $review_high = $user_info['review_high'];
                $review_medium = $user_info['review_medium'];
                $review_low = $user_info['review_low'];
		$review_insignificant = $user_info['review_insignificant'];
                $submit_risks = $user_info['submit_risks'];
                $modify_risks = $user_info['modify_risks'];
		$close_risks = $user_info['close_risks'];
                $plan_mitigations = $user_info['plan_mitigations'];
		$multi_factor = $user_info['multi_factor'];
        }
	else
	{
		$user_id = "";
                $type = "N/A";
                $username = "N/A";
                $name = "N/A";
                $email = "N/A";
                $last_login = "N/A";
                $teams = "none";
                $admin = false;
		$review_veryhigh = false;
                $review_high = false;
                $review_medium = false;
                $review_low = false;
		$review_insignificant = false;
                $submit_risks = false;
                $modify_risks = false;
                $close_risks = false;
                $plan_mitigations = false;
                $multi_factor = 1;
	}
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
    <script type="text/javascript">
      $(function(){
          $("#team").multiselect({
              allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
              includeSelectAllOption: true
          });
      });
    </script>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <script type="text/javascript">
      function checkAll(bx) {
        var cbs = document.getElementsByTagName('input');
        for(var i=0; i < cbs.length; i++) {
          if (cbs[i].type == 'checkbox') {
            cbs[i].checked = bx.checked;
          }
        }
      }

      function checkAllRiskMgmt(bx) {
        if (document.getElementsByName("check_risk_mgmt")[0].checked == true) {
          document.getElementsByName("submit_risks")[0].checked = true;
          document.getElementsByName("modify_risks")[0].checked = true;
          document.getElementsByName("close_risks")[0].checked = true;
          document.getElementsByName("plan_mitigations")[0].checked = true;
          document.getElementsByName("review_insignificant")[0].checked = true;
          document.getElementsByName("review_low")[0].checked = true;
          document.getElementsByName("review_medium")[0].checked = true;
          document.getElementsByName("review_high")[0].checked = true;
          document.getElementsByName("review_veryhigh")[0].checked = true;
        }
        else {
          document.getElementsByName("submit_risks")[0].checked = false;
          document.getElementsByName("modify_risks")[0].checked = false;
          document.getElementsByName("close_risks")[0].checked = false;
          document.getElementsByName("plan_mitigations")[0].checked = false;
          document.getElementsByName("review_insignificant")[0].checked = false;
          document.getElementsByName("review_low")[0].checked = false;
          document.getElementsByName("review_medium")[0].checked = false;
          document.getElementsByName("review_high")[0].checked = false;
          document.getElementsByName("review_veryhigh")[0].checked = false;
        }
      }

      function checkAllAssetMgmt(bx) {
        if (document.getElementsByName("check_asset_mgmt")[0].checked == true) {
          document.getElementsByName("asset")[0].checked = true;
        }
        else {
          document.getElementsByName("asset")[0].checked = false;
        }
      }

      function checkAllConfigure(bx) {
        if (document.getElementsByName("check_configure")[0].checked == true) {
          document.getElementsByName("admin")[0].checked = true;
        }
        else {
          document.getElementsByName("admin")[0].checked = false;
        }
      }
    </script>

<?php
	view_top_menu("Configure");

        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("UserManagement"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="update_user" method="post" action="">
                <input name="user" type="hidden" value="<?php echo $escaper->escapeHtml($user_id); ?>" />
                <table border="0" cellspacing="0" cellpadding="0">
                  <tr><td colspan="2"><h4>Update an Existing User:</h4></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Type']); ?>:&nbsp;</td><td><input style="cursor: default;" name="type" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($type); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($type); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td><td><input name="name" type="text" maxlength="50" size="20" value="<?php echo $escaper->escapeHtml($name); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td><td><input name="email" type="text" maxlength="200" size="20" value="<?php echo $escaper->escapeHtml($email); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td><td><input style="cursor: default;" name="username" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($username); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($username); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['LastLogin']); ?>:&nbsp;</td><td><input style="cursor: default;" name="last_login" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($last_login); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($last_login); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Language']); ?>:&nbsp;</td><td><?php create_dropdown("languages", get_value_by_name("languages", $language)); ?></td></tr>
                </table>
                <h6><u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u></h6>
                <?php create_multiple_dropdown("team", $teams); ?>
                <h6><u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u></h6>
                <table border="0" cellspacing="0" cellpadding="0">
                  <tr><td colspan="3"><input name="check_all" type="checkbox" onclick="checkAll(this)" />&nbsp;<?php echo $escaper->escapeHtml($lang['CheckAll']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td colspan="2"><input name="check_risk_mgmt" type="checkbox" onclick="checkAllRiskMgmt(this)" />&nbsp;<?php echo $escaper->escapeHtml($lang['CheckAllRiskMgmt']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="submit_risks" type="checkbox"<?php if ($submit_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToSubmitNewRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="modify_risks" type="checkbox"<?php if ($modify_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToModifyExistingRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="close_risks" type="checkbox"<?php if ($close_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToCloseRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="plan_mitigations" type="checkbox"<?php if ($plan_mitigations) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToPlanMitigations']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_insignificant" type="checkbox"<?php if ($review_insignificant) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewInsignificantRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_low" type="checkbox"<?php if ($review_low) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewLowRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_medium" type="checkbox"<?php if ($review_medium) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewMediumRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_high" type="checkbox"<?php if ($review_high) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewHighRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_veryhigh" type="checkbox"<?php if ($review_veryhigh) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewVeryHighRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td colspan="2"><input name="check_asset_mgmt" type="checkbox" onclick="checkAllAssetMgmt(this)" />&nbsp;<?php echo $escaper->escapeHtml($lang['CheckAllAssetMgmt']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="asset" type="checkbox"<?php if ($asset) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AllowAccessToAssetManagementMenu']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td colspan="2"><input name="check_configure" type="checkbox" onclick="checkAllConfigure(this)" />&nbsp;<?php echo $escaper->escapeHtml($lang['CheckAllConfigure']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="admin" type="checkbox"<?php if ($admin) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AllowAccessToConfigureMenu']); ?></td></tr>
                </table>
                <h6><u><?php echo $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></u></h6>
                <input type="radio" name="multi_factor" value="1"<?php if ($multi_factor == 1) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['None']); ?><br />
<?php
	// If the custom authentication extra is installed
	if (custom_authentication_extra())
	{
                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

		// Display the multi factor authentication options
		multi_factor_authentication_options($multi_factor);
	}
?>
                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_user" /><br />
                </p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
