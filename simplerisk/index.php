<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
 	 * License, v. 2.0. If a copy of the MPL was not distributed with this
 	 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/includes/functions.php'));
	require_once(realpath(__DIR__ . '/includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/includes/display.php'));
	require_once(realpath(__DIR__ . '/includes/alerts.php'));

	// Include Zend Escaper for HTML Output Encoding
	require_once(realpath(__DIR__ . '/includes/Component_ZendEscaper/Escaper.php'));
	$escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
		header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
        }

	// Session handler is database
	if (USE_DATABASE_FOR_SESSIONS == "true")
	{
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
	}

	// Start session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
	session_start('SimpleRisk');

	// Include the language file
	require_once(language_file());

	// If the login form was posted
	if (isset($_POST['submit']))
	{
		$user = $_POST['user'];
		$pass = $_POST['pass'];

		// If the user is valid
		if (is_valid_user($user, $pass))
		{
                	// If the custom authentication extra is installed
                	if (custom_authentication_extra())
                	{
                        	// Include the custom authentication extra
                        	require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

				// Get the enabled authentication for the user
				$enabled_auth = enabled_auth($user);

				// If no multi factor authentication is enabled for the user
				if ($enabled_auth == 1)
				{
                                	// Grant the user access
                                	grant_access();

                                	// If the encryption extra is enabled
                                	if (encryption_extra())
                                	{
                                                // Load the extra
                                                require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

						// Check user enc
						check_user_enc($user, $pass);
                                	}

                                	// Redirect to the reports index
                                	header("Location: reports");
				}
				// If Duo authentication is enabled for the user
                        	else if ($enabled_auth == 2)
                        	{
					// Set session access to duo
					$_SESSION["access"] = "duo";
                        	}
				// If Toopher authentication is enabled for the user
				else if ($enabled_auth == 3)
				{
                                        // Set session access to toopher
                                        $_SESSION["access"] = "toopher";
				}
			}
			// Otherwise no second factor is necessary
			else
			{
				// Grant the user access
				grant_access();

                                // If the encryption extra is enabled
                                if (encryption_extra())
                                {
                                        // Load the extra
                                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                                	// Check user enc
                                        check_user_enc($user, $pass);
                                }

				// Redirect to the reports index
				header("Location: reports");
			}
		}
		// If the user is not a valid user
		else
		{
			$_SESSION["access"] = "denied";

			// Display an alert
			set_alert(true, "bad", "Invalid username or password.");
		}
	}

	// If the user has already authorized and we are authorizing with duo
	if (isset($_SESSION["access"]) && ($_SESSION["access"] == "duo"))
	{
		// If a response has been posted
		if (isset($_POST['sig_response']))
		{
	                // Include the custom authentication extra
        	        require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

                        // Get the authentication settings
                        $configs = get_authentication_settings();

                        // For each configuration
                        foreach ($configs as $config)
                        {
                                // Set the name value pair as a variable
                                $$config['name'] = $config['value'];
                        }

			// Get the response back from Duo
        		$resp = Duo::verifyResponse($IKEY, $SKEY, get_duo_akey(), $_POST['sig_response']);

			// If the response is not null
        		if ($resp != NULL)
			{
                        	// Grant the user access
                        	grant_access();

                                // If the encryption extra is enabled
                                if (encryption_extra())
                                {
                                        // Load the extra
                                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                                	// Check user enc
                                        check_user_enc($user, $pass);
                                }

                        	// Redirect to the reports index
                        	header("Location: reports");
			}
		}
	}
?>

<!doctype html>
<html>
  
  <head>
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css"> 
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css">
    <link rel="stylesheet" href="css/divshot-util.css">
    <link rel="stylesheet" href="css/divshot-canvas.css">
    <link rel="stylesheet" href="css/display.css">

    <?php view_top_menu("Home"); ?>

    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span9">
          <div class="hero-unit">
            <center>
            <img src="images/SimpleRiskLogo.png" style="width:500px;" />
            <p>Enterprise Risk Management Simplified.</p>
            </center>
          </div>
        </div>
      </div>
<?php
	// If the user has authenticated and now we need to authenticate with duo
	if (isset($_SESSION["access"]) && $_SESSION["access"] == "duo")
	{
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span9\">\n";
                echo "<div class=\"well\">\n";

                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

        	// Perform a duo authentication request for the user
        	duo_authentication($_SESSION["user"]);

                echo "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
	}
	// If the user has not authenticated
	else if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
	{
      		echo "<div class=\"row-fluid\">\n";
      		echo "<div class=\"span9\">\n";
      		echo "<div class=\"well\">\n";

		// Get any alert messages
		get_alert();

      		echo "<form name=\"authenticate\" method=\"post\" action=\"\">\n";
		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
		echo "<tr><td colspan=\"2\"><label><u>" . $escaper->escapeHtml($lang['LogInHere']) . "</u></label></td></tr>\n";
      		echo "<tr><td>" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</td><td><input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /></td></tr>\n";
      		echo "<tr><td>" . $escaper->escapeHtml($lang['Password']) . ":&nbsp;</td><td><input class=\"input-medium\" name=\"pass\" id=\"pass\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
		echo "<tr><td colspan=\"2\"><label><a href=\"reset.php\">" . $escaper->escapeHtml($lang['ForgotYourPassword']) . "</a></label></td></tr>\n";
		echo "</table>\n";
      		echo "<div class=\"form-actions\">\n";
      		echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Login']) . "</button>\n";
      		echo "<input class=\"btn\" value=\"" . $escaper->escapeHtml($lang['Reset']) . "\" type=\"reset\">\n";
      		echo "</div>\n";
      		echo "</form>\n";
      		echo "</div>\n";
      		echo "</div>\n";
      		echo "</div>\n";
	}
?>
    </div>
  </body>

</html>
