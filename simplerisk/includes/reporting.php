<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/HighchartsPHP/Highchart.php'));
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/****************************
 * FUNCTION: GET OPEN RISKS *
 ****************************/
function get_open_risks()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM `risks` WHERE status != \"Closed\"");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($array);
}

/******************************
 * FUNCTION: GET CLOSED RISKS *
 ******************************/
function get_closed_risks()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM `risks` WHERE status = \"Closed\"");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($array);
}

/****************************
 * FUNCTION: GET HIGH RISKS *
 ****************************/
function get_high_risks()
{
        // Open the database connection
        $db = db_open();

        // Get the high risk level
        $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High' OR name = 'Very High'");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $high = $array[0]['value'];
	$veryhigh = $array[1]['value'];

        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high AND a.calculated_risk < :veryhigh");
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
	$stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($array);
}

/*********************************
 * FUNCTION: GET VERY HIGH RISKS *
 *********************************/
function get_veryhigh_risks()
{
        // Open the database connection
        $db = db_open();

        // Get the high risk level
        $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Very High'");
        $stmt->execute();
        $array = $stmt->fetch();
        $veryhigh = $array['value'];

        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :veryhigh");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($array);
}

/****************************
 * FUNCTION: GET RISK TREND *
 ****************************/
function get_risk_trend($title = null)
{
        $chart = new Highchart();
	$chart->includeExtraScripts();

	$chart->chart->type = "arearange";
	$chart->chart->zoomType = "x";
	$chart->title->text = $title;
	$chart->xAxis->type = "datetime";
	$chart->yAxis->title->text = null;
	$chart->yAxis->min = 0;
	$chart->tooltip = array(
		'crosshairs' => true,
		'shared' => true,
		'valueSuffix' => ' risk(s)'
	);
	$chart->legend->enabled = false;
        $chart->chart->renderTo = "risk_trend_chart";
        $chart->credits->enabled = false;
	$chart->plotOptions->series->marker->enabled = false;
	$chart->plotOptions->series->marker->lineWidth = "2";
	// These set the marker symbol when selected
	$chart->plotOptions->series->marker->symbol = "circle";
	$chart->plotOptions->series->marker->states->hover->enabled = true;
	$chart->plotOptions->series->marker->states->hover->fillColor = "white";
	$chart->plotOptions->series->marker->states->hover->lineColor = "black";
	$chart->plotOptions->series->marker->states->hover->lineWidth = "2";

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT DATE(submission_date) date, COUNT(DISTINCT id) count FROM `risks` GROUP BY DATE(submission_date) ORDER BY DATE(submission_date)");
        $stmt->execute();

        // Store the list in the array
        $opened_risks = $stmt->fetchAll();

        // Query the database
	$stmt = $db->prepare("SELECT DATE(a.closure_date) date, COUNT(DISTINCT b.id) count FROM `closures` a JOIN `risks` b ON a.risk_id = b.id WHERE b.status = \"Closed\" GROUP BY DATE(a.closure_date)");
        $stmt->execute();

        // Store the list in the array
        $closed_risks = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the opened risks array is empty
        if (empty($opened_risks))
        {
                $opened_risk_data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
		// Set the sum to 0
		$opened_sum = 0;
		$closed_sum = 0;

		// Set the start date
		$date = $opened_risks[0]['date'];

		// For each date from the start date until today
		while (strtotime($date) <= time())
		{
			// If the PHP version is >= 5.5.0
			// array_column is new as of PHP 5.5
			if (strnatcmp(phpversion(),'5.5.0') >= 0) 
			{
				// Search the opened array for the value
				$opened_search = array_search($date, array_column($opened_risks, 'date'));
			}
			else $opened_search = false;

			// If the current date is in the opened array
			if ($opened_search !== false)
			{
				$count = $opened_risks[$opened_search]['count'];
				$opened_sum += $count;
			}

                        // If the PHP version is >= 5.5.0
                        // array_column is new as of PHP 5.5
                        if (strnatcmp(phpversion(),'5.5.0') >= 0) 
                        {
                        	// Search the closed array for the value
                        	$closed_search = array_search($date, array_column($closed_risks, 'date'));
			}
			else $closed_search = false;

			// If the current date is in the closed array
			if ($closed_search !== false)
                        {
                                $count = $closed_risks[$closed_search]['count'];
                                $closed_sum += $count;
                        }

			// Create the data arrays
			$opened_risk_data[] = array(strtotime($date) * 1000, $opened_sum);
			$closed_risk_data[] = array(strtotime($date) * 1000, $closed_sum);
			$trend_data[] = array(strtotime($date) * 1000, $opened_sum - $closed_sum);

			// Increment the date one day
			$date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
		}

		// Draw the open risks line
                $chart->series[] = array(
			'type' => "line",
                        'name' => "Opened Risks",
			'color' => "red",
			'lineWidth' => "2",
                        'data' => $opened_risk_data
		);

		// Draw the closed risks line
                $chart->series[] = array(
                        'type' => "line",
                        'name' => "Closed Risks",
			'color' => "blue",
			'lineWidth' => "2",
                        'data' => $closed_risk_data
		);

		// Draw the trend line
		$chart->series[] = array(
                        'type' => "line",
                        'name' => "Trend",
			'color' => "#000000",
			'lineWidth' => "2",
                        'data' => $trend_data
                );
        }

	$chart->printScripts();
	echo "<div id=\"risk_trend_chart\"></div>\n";
	echo "<script type=\"text/javascript\">";
	echo $chart->render("risk_trend_chart");
	echo "</script>\n";
	echo "<br /><p><font size=\"1\">* This report requires PHP >= 5.5 in order to run properly.</font></p>\n";
}

/******************************
 * FUNCTION: GET RISK PYRAMID *
 ******************************/
function get_risk_pyramid($title = null)
{
        $chart = new Highchart();
        $chart->includeExtraScripts();

        $chart->chart->type = "funnel";
	$chart->chart->marginRight = "100";
        $chart->title->text = $title;
	$chart->title->x = "-50";
        $chart->chart->renderTo = "risk_pyramid_chart";
        $chart->credits->enabled = false;
	$chart->plotOptions->series->dataLabels->enabled = true;
	$chart->plotOptions->series->dataLabels->format = "<b>{point.name}</b> ({point.y:,.0f})";
	$chart->plotOptions->series->dataLabels->color = "(Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'";
	$chart->plotOptions->series->dataLabels->softConnector = true;
	$chart->legend->enabled = false;

        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $veryhigh = $array[0][0];
        $high = $array[1][0];
        $medium = $array[2][0];
        $low = $array[3][0];

        // Query the database
        $stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Initialize veryhigh, high, medium, low, and insignificant
                $veryhigh = false;
                $high = false;
                $medium = false;
                $low = false;
                $color_array = array();

                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['level'], (int)$row['num']);

                        // If we have at least one very high risk
                        if ($row['level'] == "Very High" && $veryhigh != true)
                        {
                                $veryhigh = true;

                                // Add red to the color array
                                $color_array[] = "red";
                        }
                        // If we have at least one high risk
                        else if ($row['level'] == "High" && $high != true)
                        {
                                $high = true;

                                // Add red to the color array
                                $color_array[] = "orangered";
                        }
                        // If we have at least one medium risk
                        else if ($row['level'] == "Medium" && $medium != true)
                        {
                                $medium = true;

                                // Add orange to the color array
                                $color_array[] = "orange";
                        }
                        // If we have at least one low risk
                        else if ($row['level'] == "Low" && $low != true)
                        {
                                $low = true;

                                // Add yellow to the color array
                                $color_array[] = "yellow";
                        }
                }

                // Add black to color array for insignificant
                $color_array[] = "lightgrey";

                $chart->plotOptions->pie->colors = $color_array;

                $chart->series[] = array(
                        'name' => "Risk Pyramid",
                        'data' => $data);
	}

        $chart->printScripts();
        echo "<div id=\"risk_pyramid_chart\"></div>\n";
        echo "<script type=\"text/javascript\">";
        echo $chart->render("risk_pyramid_chart");
        echo "</script>\n";
        echo "<br /><p><font size=\"1\">* This report requires PHP >= 5.5 in order to run properly.</font></p>\n";
}

/**********************************
 * FUNCTION: OPEN RISK LEVEL PIE *
 **********************************/
function open_risk_level_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_level_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

	$chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
	location.href = 'dynamic_risk_report.php?status=0&group=1&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

	// Get the risk levels
	$stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
	$stmt->execute();
	$array = $stmt->fetchAll();
	$veryhigh = $array[0][0];
	$high = $array[1][0];
	$medium = $array[2][0];
	$low = $array[3][0];

        // Query the database
        $stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
	$stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
	$stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
	$stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
	$stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
		// Initialize veryhigh, high, medium, low, and insignificant
		$veryhigh = false;
		$high = false;
		$medium = false;
		$low = false;
		$color_array = array();

                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['level'], (int)$row['num']);

			// If we have at least one very high risk
			if ($row['level'] == "Very High" && $veryhigh != true)
			{
				$veryhigh = true;

				// Add red to the color array
				$color_array[] = "red";
			}
			// If we have at least one high risk
			else if ($row['level'] == "High" && $high != true)
			{
				$high = true;

				// Add red to the color array
				$color_array[] = "orangered";
			}
			// If we have at least one medium risk
			else if ($row['level'] == "Medium" && $medium != true)
			{
				$medium = true;

				// Add orange to the color array
				$color_array[] = "orange";
			}
			// If we have at least one low risk
			else if ($row['level'] == "Low" && $low != true)
			{
				$low = true;

				// Add yellow to the color array
				$color_array[] = "yellow";
			}
                }

		// Add black to color array for insignificant
		$color_array[] = "lightgrey";

		$chart->plotOptions->pie->colors = $color_array;

                $chart->series[] = array('type' => "pie",
                        'name' => "Level",
                        'data' => $data);
        }

    	echo "<div id=\"open_risk_level_pie\"></div>\n";
    	echo "<script type=\"text/javascript\">";
    	echo $chart->render("open_risk_level_pie");
    	echo "</script>\n";
}

/**********************************
 * FUNCTION: OPEN RISK STATUS PIE *
 **********************************/
function open_risk_status_pie($title = null)
{
	$chart = new Highchart();

	$chart->chart->renderTo = "open_risk_status_pie";
	$chart->chart->plotBackgroundColor = null;
	$chart->chart->plotBorderWidth = null;
	$chart->chart->plotShadow = false;
	$chart->title->text = $title;

	$chart->tooltip->formatter = new HighchartJsExpr("function() {
    	return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=2&sort=0'; }");

	$chart->plotOptions->pie->allowPointSelect = 1;
	$chart->plotOptions->pie->cursor = "pointer";
	$chart->plotOptions->pie->dataLabels->enabled = false;
	$chart->plotOptions->pie->showInLegend = 1;
	$chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT status, COUNT(*) AS num FROM `risks` WHERE status != \"Closed\" GROUP BY status ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
		$data[] = array("No Data Available", 0);
	}
	// Otherwise
	else
	{
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['status'], (int)$row['num']);
        	}

		$chart->series[] = array('type' => "pie",
			'name' => "Status",
			'data' => $data);
	}

    echo "<div id=\"open_risk_status_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_status_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: CLOSED RISK REASON PIE *
 ************************************/
function closed_risk_reason_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "closed_risk_reason_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=1&group=0&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
	$stmt = $db->prepare("SELECT a.close_reason, b.id, b.status, c.name, COUNT(*) AS num FROM `closures` a JOIN `risks` b ON a.risk_id = b.id JOIN `close_reason` c ON a.close_reason= c.value WHERE b.status = \"Closed\" GROUP BY c.name ORDER BY COUNT(*) DESC;");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['name'], (int)$row['num']);
        	}

        	$chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
	}

    echo "<div id=\"closed_risk_reason_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("closed_risk_reason_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: OPEN RISK LOCATION PIE *
 ************************************/
function open_risk_location_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_location_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=3&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `location` b ON a.location = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['name'], (int)$row['num']);
        	}

        	$chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
	}

    echo "<div id=\"open_risk_location_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_location_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: OPEN RISK CATEGORY PIE *
 ************************************/
function open_risk_category_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_category_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=4&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['name'], (int)$row['num']);
        	}

        	$chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
	}

    echo "<div id=\"open_risk_category_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_category_pie");
    echo "</script>\n";
}

/********************************
 * FUNCTION: OPEN RISK TEAM PIE *
 ********************************/
function open_risk_team_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_team_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=5&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `team` b ON a.team = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['name'], (int)$row['num']);
        	}

        	$chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
	}

    echo "<div id=\"open_risk_team_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_team_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK TECHNOLOGY PIE *
 **************************************/
function open_risk_technology_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_technology_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=6&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `technology` b ON a.technology = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
        	// Create the data array
        	foreach ($array as $row)
        	{
                	$data[] = array($row['name'], (int)$row['num']);
        	}

        	$chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
	}

    echo "<div id=\"open_risk_technology_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_technology_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK OWNER PIE *
 **************************************/
function open_risk_owner_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owner_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=7&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);
                }

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_risk_owner_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owner_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK OWNERS MANAGER PIE *
 ******************************************/
function open_risk_owners_manager_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owners_manager_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=8&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);
                }

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_risk_owners_manager_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owners_manager_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK SCORING METHOD PIE *
 ******************************************/
function open_risk_scoring_method_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_scoring_method_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=9&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a INNER JOIN `risk_scoring` b ON a.id = b.id WHERE status != \"Closed\" GROUP BY b.scoring_method ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);
                }

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_risk_scoring_method_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_scoring_method_pie");
    echo "</script>\n";
}

/*************************************
 * FUNCTION: GET REVIEW NEEDED TABLE *
 *************************************/
function get_review_needed_table()
{
        global $lang;
	global $escaper;

        // Get risks marked as consider for projects
        $risks = get_risks(3);

        // Initialize the reviews array
        $reviews = array();

	// Parse through each row in the array
	foreach ($risks as $key => $row)
	{
		// Create arrays for each value
		$risk_id[$key] = (int)$row['id'];
		$subject[$key] = $row['subject'];
                $status[$key] = $row['status'];
                $calculated_risk[$key] = $row['calculated_risk'];
                $color[$key] = get_risk_color($row['calculated_risk']);
                $dayssince[$key] = dayssince($row['submission_date']);
                $next_review[$key] = next_review($color[$key], $risk_id[$key], $row['next_review'], false);
                $next_review_html[$key] = next_review($color[$key], $row['id'], $row['next_review']);

		// Create a new array of reviews
		$reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);

		// Sort the reviews array by next_review
		array_multisort($next_review, SORT_DESC, SORT_STRING, $calculated_risk, SORT_DESC, SORT_NUMERIC, $reviews);
	}

	// Start with an empty review status;
	$review_status = "";

        // For each risk
        foreach ($reviews as $review)
        {
                $risk_id = $review['risk_id'];
		$subject = $review['subject'];
		$status = $review['status'];
		$calculated_risk = $review['calculated_risk'];
                $color = $review['color'];
		$dayssince = $review['dayssince'];
		$next_review = $review['next_review'];
		$next_review_html = $review['next_review_html'];

		// If we have a new review status and its not a date
		if (($review_status != $next_review) && (!preg_match('/\d{4}/', $review_status)))
		{
			// If its not the first risk
			if ($review_status != "")
			{
				// End the previous table
        			echo "</tbody>\n";
        			echo "</table>\n";
        			echo "<br />\n";

			}

			// Set the new review status
			$review_status = $next_review;

			// If the review status is not a date
			if (!preg_match('/\d{4}/', $review_status))
			{
				// Start the new table
        			echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        			echo "<thead>\n";
        			echo "<tr>\n";
        			echo "<th bgcolor=\"#0088CC\" colspan=\"6\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($review_status) ."</font></center></th>\n";
        			echo "</tr>\n";
        			echo "<tr>\n";
        			echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        			echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
        			echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        			echo "<th align=\"center\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        			echo "<th align=\"center\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
        			echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
        			echo "</tr>\n";
        			echo "</thead>\n";
        			echo "<tbody>\n";
			}
		}

		// If the review status is not a date
		if (!preg_match('/\d{4}/', $review_status))
                {
                	echo "<tr>\n";
                	echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
			echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
                	echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                	echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "</td>\n";
			echo "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
                	echo "<td align=\"center\" width=\"150px\">" . $next_review_html . "</td>\n";
                	echo "</tr>\n";
		}
        }
}

/********************************
 * FUNCTION: GET RISKS BY TABLE *
 ********************************/
function get_risks_by_table($status, $group, $sort, $column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_category=false, $column_team=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false)
{
	global $lang;
	global $escaper;

	// Check the status
	switch ($status)
	{
		// Open risks
		case 0:
			$status_query = " WHERE a.status != \"Closed\" ";
			break;
		// Closed risks
		case 1:
			$status_query = " WHERE a.status = \"Closed\" ";
			break;
		case 2:
		// All risks
			$status_query = "";
			break;
		// Default to open risks
		default:
			$status_query = " WHERE a.status != \"Closed\" ";
			break;
	}


        // Check the sort
        switch ($sort)
        {
                // Calculated Risk
                case 0:
			$sort_name = " calculated_risk DESC ";
                        break;
		// ID
		case 1:
			$sort_name = " a.id ASC ";
			break;
		// Subject
		case 2:
			$sort_name = " a.subject ASC ";
			break;
                // Default to calculated risk
                default:
			$sort_name = " calculated_risk DESC ";
                        break;
        }

	// Check the group
	switch ($group)
	{
		// None
		case 0:
			$order_query = "GROUP BY id ORDER BY" . $sort_name;
			$group_name = "none";
			break;
		// Risk Level
		case 1:
			$order_query = "GROUP BY id ORDER BY" . $sort_name;
			$group_name = "risk_level";
			break;
		// Status
		case 2:
			$order_query = "GROUP BY id ORDER BY a.status," . $sort_name;
			$group_name = "status";
			break;
		// Site/Location
		case 3:
			$order_query = "GROUP BY id ORDER BY location," . $sort_name;
			$group_name = "location";
			break;
		// Category
		case 4:
			$order_query = "GROUP BY id ORDER BY category," . $sort_name;
			$group_name = "category";
			break;
		// Team
		case 5:
			$order_query = "GROUP BY id ORDER BY team," . $sort_name;
			$group_name = "team";
			break;
		// Technology
		case 6:
			$order_query = "GROUP BY id ORDER BY technology," . $sort_name;
			$group_name = "technology";
			break;
		// Owner
		case 7:
			$order_query = "GROUP BY id ORDER BY owner," . $sort_name;
			$group_name = "owner";
			break;
		// Owners Manager
		case 8:
			$order_query = "GROUP BY id ORDER BY manager," . $sort_name;
			$group_name = "manager";
			break;
		// Risk Scoring Method
		case 9:
			$order_query = "GROUP BY id ORDER BY scoring_method," . $sort_name;
			$group_name = "scoring_method";
			break;
		// Regulation
		case 10:
			$order_query = "GROUP BY id ORDER BY regulation," . $sort_name;
			$group_name = "regulation";
			break;
		// Project
		case 11:
			$order_query = "GROUP BY id ORDER BY project," . $sort_name;
			$group_name = "project";
			break;
		// Next Step
		case 12:
			$order_query = "GROUP BY id ORDER BY next_step," . $sort_name;
			$group_name = "next_step";
			break;
		// Month Submitted
		case 13:
			$order_query = "GROUP BY id ORDER BY submission_date DESC," . $sort_name;
			$group_name = "month_submitted";
			break;
		// Default to calculated risk
		default:
			$order_query = "GROUP BY id ORDER BY" . $sort_name;
			$group_name = "none";
			break;
	}

	// Make the big query
	$query = "SELECT a.id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mitigation_id, a.mgmt_review, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(n.asset SEPARATOR ', ') AS affected_assets FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id" . $status_query . $order_query;

	// Query the database
	$db = db_open();
	$stmt = $db->prepare($query);
	$stmt->execute();
	db_close($db);

	// Store the results in the risks array
	$risks = $stmt->fetchAll();

	// If team separation is enabled
	if (team_separation_extra())
        {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Strip out risks the user should not have access to
                $risks = strip_no_access_risks($risks);
        }

	// Set the current group to empty
	$current_group = "";

	// If the group name is none
	if ($group_name == "none")
	{
		// Display the table header
		echo "<table class=\"table table-bordered table-condensed sortable\">\n";
		echo "<thead>\n";
		echo "<tr>\n";

		// Header columns go here
		get_header_columns($column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets);

		echo "</tr>\n";
		echo "</thead>\n";
		echo "<tbody>\n";
	}

	// For each risk in the risks array
	foreach ($risks as $risk)
	{
		$risk_id = (int)$risk['id'];
		$status = $risk['status'];
		$subject = $risk['subject'];
		$reference_id = $risk['reference_id'];
		$control_number = $risk['control_number'];
		$submission_date = $risk['submission_date'];
		$last_update = $risk['last_update'];
		$review_date = $risk['review_date'];
		$scoring_method = get_scoring_method_name($risk['scoring_method']);
		$calculated_risk = (float)$risk['calculated_risk'];
		$color = get_risk_color($risk['calculated_risk']);
		$risk_level = get_risk_level_name($risk['calculated_risk']);
		$location = $risk['location'];
		$category = $risk['category'];
		$team = $risk['team'];
		$technology = $risk['technology'];
		$owner = $risk['owner'];
		$manager = $risk['manager'];
		$submitted_by = $risk['submitted_by'];
		$regulation = $risk['regulation'];
		$project = $risk['project'];
		$mitigation_id = $risk['mitigation_id'];
		$mgmt_review = $risk['mgmt_review'];
		$days_open = dayssince($risk['submission_date']);
		$next_review_date = next_review($color, $risk_id, $risk['next_review'], false);
		$next_review_date_html = next_review($color, $risk_id, $risk['next_review']);
		$next_step = $risk['next_step'];
		$affected_assets = $risk['affected_assets'];
		$month_submitted = date('Y F', strtotime($risk['submission_date']));

		// If the group name is not none
		if ($group_name != "none")
		{
			$group_value = ${$group_name};

			// If the selected group value is empty
			if ($group_value == "")
			{
				// Current group is Unassigned
				$group_value = $lang['Unassigned'];
			}

			// If the group is not the current group
			if ($group_value != $current_group)
			{
				// If this is not the first group
				if ($current_group != "")
				{
					echo "</tbody>\n";
					echo "</table>\n";
					echo "<br />\n";
				}

				// If the group is not empty
				if ($group_value != "")
				{
					// Set the group to the current group
					$current_group = $group_value;
				}
				else $current_group = $lang['Unassigned'];

				// Display the table header
				echo "<table class=\"table table-bordered table-condensed sortable\">\n";
				echo "<thead>\n";
				echo "<tr>\n";
				echo "<th bgcolor=\"#0088CC\" colspan=\"100%\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($current_group) ."</font></center></th>\n";
				echo "</tr>\n";
				echo "<tr>\n";

				// Header columns go here
				get_header_columns($column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets);

				echo "</tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
			}
		}

		// Display the risk information
		echo "<tr>\n";

		// Risk information goes here
		get_risk_columns($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets);

		echo "</tr>\n";
	
	}

	// If the group name is none
	if ($group_name == "none")
	{
		// End the table
		echo "</tbody>\n";
		echo "</table>\n";
		echo "<br />\n";
	}
}

/********************************
 * FUNCTION: GET HEADER COLUMNS *
 ********************************/
function get_header_columns($id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets)
{
	global $lang;
	global $escaper;

	echo "<th class=\"id\" " . ($id == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"25px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
	echo "<th class=\"status\" " . ($risk_status == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
        echo "<th class=\"subject\" " . ($subject == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th class=\"reference_id\" " . ($reference_id == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ExternalReferenceId']) ."</th>\n";
        echo "<th class=\"regulation\" " . ($regulation == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ControlRegulation']) ."</th>\n";
        echo "<th class=\"control_number\" " . ($control_number == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ControlNumber']) ."</th>\n";
        echo "<th class=\"location\" " . ($location == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
        echo "<th class=\"category\" " . ($category == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Category']) ."</th>\n";
        echo "<th class=\"team\" " . ($team == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
        echo "<th class=\"technology\" " . ($technology == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Technology']) ."</th>\n";
        echo "<th class=\"owner\" " . ($owner == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Owner']) ."</th>\n";
        echo "<th class=\"manager\" " . ($manager == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['OwnersManager']) ."</th>\n";
        echo "<th class=\"submitted_by\" " . ($submitted_by == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SubmittedBy']) ."</th>\n";
        echo "<th class=\"scoring_method\" " . ($scoring_method == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['RiskScoringMethod']) ."</th>\n";
        echo "<th class=\"calculated_risk\" " . ($calculated_risk == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"25px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th class=\"submission_date\" " . ($submission_date == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "<th class=\"review_date\" " . ($review_date == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ReviewDate']) ."</th>\n";
	echo "<th class=\"project\" " . ($project == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Project']) ."</th>\n";
	echo "<th class=\"mitigation_planned\" " . ($mitigation_planned == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationPlanned']) ."</th>\n";
	echo "<th class=\"management_review\" " . ($management_review == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ManagementReview']) ."</th>\n";
	echo "<th class=\"days_open\" " . ($days_open == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
	echo "<th class=\"next_review_date\" " . ($next_review_date == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
	echo "<th class=\"next_step\" " . ($next_step == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['NextStep']) ."</th>\n";
	echo "<th class=\"affected_assets\" " . ($affected_assets == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['AffectedAssets']) ."</th>\n";
}

/******************************
 * FUNCTION: GET RISK COLUMNS *
 ******************************/
function get_risk_columns($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets)
{
        global $lang;
        global $escaper;

	$risk_id = (int)$risk['id'];
	$status = $risk['status'];
	$subject = $risk['subject'];
	$reference_id = $risk['reference_id'];
	$control_number = $risk['control_number'];
	$submission_date = $risk['submission_date'];
	$last_update = $risk['last_update'];
	$review_date = $risk['review_date'];
	$scoring_method = get_scoring_method_name($risk['scoring_method']);
	$calculated_risk = $risk['calculated_risk'];
	$color = get_risk_color($risk['calculated_risk']);
	$risk_level = get_risk_level_name($risk['calculated_risk']);
	$location = $risk['location'];
	$category = $risk['category'];
	$team = $risk['team'];
	$technology = $risk['technology'];
	$owner = $risk['owner'];
	$manager = $risk['manager'];
	$submitted_by = $risk['submitted_by'];
	$regulation = $risk['regulation'];
	$project = $risk['project'];
	$mitigation_id = $risk['mitigation_id'];
	$mgmt_review = $risk['mgmt_review'];
	$days_open = dayssince($risk['submission_date']);
	$next_review_date = next_review($color, $risk_id, $risk['next_review'], false);
	$next_review_date_html = next_review($color, $risk_id, $risk['next_review']);
	$next_step = $risk['next_step'];
	$affected_assets = $risk['affected_assets'];

	// If the risk hasn't been reviewed yet
	if ($review_date == "0000-00-00 00:00:00")
	{
		// Set the review date to empty
		$review_date = "";
	}
	// Otherwise set the review date to the proper format
	else $review_date = date(DATETIMESIMPLE, strtotime($review_date));

	echo "<td class=\"id\" " . ($column_id == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"25px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\" target=\"_blank\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
	echo "<td class=\"status\" " . ($column_status == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($status) . "</td>\n";
	echo "<td class=\"subject\" " . ($column_subject == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
	echo "<td class=\"reference_id\" " . ($column_reference_id == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($reference_id) . "</td>\n";
	echo "<td class=\"regulation\" " . ($column_regulation == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($regulation) . "</td>\n";
	echo "<td class=\"control_number\" " . ($column_control_number == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($control_number) . "</td>\n";
	echo "<td class=\"location\" " . ($column_location == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($location) . "</td>\n";
	echo "<td class=\"category\" " . ($column_category == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($category) . "</td>\n";
	echo "<td class=\"team\" " . ($column_team == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($team) . "</td>\n";
	echo "<td class=\"technology\" " . ($column_technology == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($technology) . "</td>\n";
	echo "<td class=\"owner\" " . ($column_owner == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($owner) . "</td>\n";
	echo "<td class=\"manager\" " . ($column_manager == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($manager) . "</td>\n";
	echo "<td class=\"submitted_by\" " . ($column_submitted_by == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($submitted_by) . "</td>\n";
	echo "<td class=\"scoring_method\" " . ($column_scoring_method == true ? "" : "style=\"display:none;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($scoring_method) . "</td>\n";
	echo "<td class=\"calculated_risk\" " . ($column_calculated_risk == true ? "" : "style=\"display:none;\" ") . "align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"25px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
	echo "<td class=\"submission_date\" " . ($column_submission_date == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(DATETIMESIMPLE, strtotime($submission_date))) . "</td>\n";
	echo "<td class=\"review_date\" " . ($column_review_date == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($review_date) . "</td>\n";
	echo "<td class=\"project\" " . ($column_project == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($project) . "</td>\n";
	echo "<td class=\"mitigation_planned\" " . ($column_mitigation_planned == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . planned_mitigation(convert_id($risk_id), $mitigation_id) . "</td>\n";
	echo "<td class=\"management_review\" " . ($column_management_review == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . management_review(convert_id($risk_id), $mgmt_review) . "</td>\n";
	echo "<td class=\"days_open\" " . ($column_days_open == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($days_open) . "</td>\n";
	echo "<td class=\"next_review_date\" " . ($column_next_review_date == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $next_review_date_html . "</td>\n";
	echo "<td class=\"next_step\" " . ($column_next_step == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($next_step) . "</td>\n";
	echo "<td class=\"affected_assets\" " . ($column_affected_assets == true ? "" : "style=\"display:none;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($affected_assets) . "</td>\n";
}

?>
