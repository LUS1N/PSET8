<?php

    require(__DIR__ . "/../includes/config.php");

    // ensure proper usage
    if (empty($_GET["geo"]))
    {
        http_response_code(400);
        exit;
    }

    // numerically indexed array of places
    $places = [];

    // use regex to split by space or comma
    $names = preg_split("/[\s,]+/", $_GET["geo"]);

    // extracts all the values to it
    $extracted = extractGeoValues($names);

    if ($extracted !== null)
    {
        // creates sql statement for the values provided
        $sql = createSQLStatement($extracted);
        $places = query($sql);
    }


    // output places as JSON (pretty-printed for debugging convenience)
    header("Content-type: application/json");
    print(json_encode($places, JSON_PRETTY_PRINT));


    /**
     * @param $extracted
     *
     * @return string
     * prepares the sql statement for the geo location
     */
    function createSQLStatement($extracted)
    {
        $sql = "SELECT * FROM places WHERE ";

        // add each field to selection
        foreach ($extracted as $key => $val)
        {
            $sql = $sql . $key . " = '$val' AND ";
        }

        // trim the last AND
        $sql = substr($sql, 0, strlen($sql) - 4);

        return $sql;
    }

    /**
     * @param $names
     *
     * @return mixed
     *
     * extracts the geo values to the fields names
     * by their concurrent names in the DB
     */
    function extractGeoValues($names)
    {
        if (empty($names))
        {
            return null;
        }
        $extracted = [];

        foreach ($names as $val)
        {
            // check if it's a number
            if ((string)(int)$val == $val)
            {
                $extracted["postal_code"] = $val;
            }
            // check if it's the state code
            else if (strlen($val) == 2)
            {
                if (strcmp($val, 'US') != 0)
                {
                    $extracted["admin_code1"] = $val;
                }
            }
            else
            {
                extractStateAndPlace($val, $extracted);
            }
        }

        return $extracted;
    }

    /**
     * @param $val
     * @param $extracted
     *
     * extracts the values as state code or place name
     */
    function extractStateAndPlace($val, &$extracted)
    {
        // check if string is a state name
        if (count(query("SELECT state FROM states WHERE state = ? ", $val)) == 1)
        {
            $extracted["admin_name1"] = $val;
        }
        else
        {
            // if it's not append it to the place name
            if (isset($extracted["place_name"]))
            {
                $extracted["place_name"] = $extracted["place_name"] . " " . $val;
            }
            else
            {
                $extracted["place_name"] = $val;
            }
        }
    }
