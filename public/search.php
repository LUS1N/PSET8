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

    // only look for things if the query is more than 4 chars
    if (strlen($_GET['geo']) >= 4)
    {
        // use regex to split by space or comma
        $names = preg_split("/[\s,]+/", $_GET["geo"]);

        // extracts all the values to it
        $extracted = extractGeoValues($names);
        $places = createAndExecuteQuery($extracted);
    }

    // output places as JSON (pretty-printed for debugging convenience)
    header("Content-type: application/json");
    print(json_encode($places, JSON_PRETTY_PRINT));

    /**
     * @param $extracted
     * Checks the geo string for a state value
     */
    function checkState(&$extracted)
    {
        global $stateNames;
        $matches = [];
        foreach ($stateNames as $state)
        {
            $regex = "/" . $state . "/";
            if (preg_match($regex, $_GET['geo'], $matches))
            {
                $extracted['admin_name1'] = $matches[0];

                return;
            }
        }
    }

    /**
     * @param $extracted
     *
     * @return string
     * prepares the sql statement for the geo location
     */
    function createAndExecuteQuery($extracted)
    {
        if (isset($extracted['postal_code']))
        {
            $places = query((SQL . "postal_code = ?"), $extracted["postal_code"]);
        }
        else if (isset($extracted['admin_code1']))
        {
            $places = query((SQL . " MATCH (place_name, admin_name1, admin_name2) AGAINST (?) AND admin_code1 = ?"),
                $extracted['place_name'], $extracted['admin_code1']);
        }
        else if (isset($extracted['admin_name1']))
        {
            $places = query((SQL . " MATCH (place_name, admin_name1, admin_name2) AGAINST (?) AND admin_name1 = ?"),
                $extracted['place_name'], $extracted['admin_name1']);
        }
        else
        {
            $places = query((SQL . " MATCH (place_name, admin_name1, admin_name2) AGAINST (?)"), $extracted['place_name']);
        }

        return $places;
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
        global $stateCodes;
        if (empty($names))
        {
            return null;
        }
        $extracted = [];

        // extract state if it's there
        checkState($extracted);

        foreach ($names as $val)
        {
            // check if it's a number
            if ((string)(int)$val == $val)
            {
                $extracted["postal_code"] = $val;

                // return right away because that's the most specific we can get
                return $extracted;
            }
            // check if it's the state code
            else if (strlen($val) == 2 && strcmp($val, 'US') != 0)
            {
                if (in_array(strtoupper($val), $stateCodes))
                {
                    $extracted["admin_code1"] = $val;
                }
            }
            else
            {
                extractPlaceName($val, $extracted);
            }
        }
        cutOutState($extracted);

        return $extracted;
    }

    /**
     * @param $extracted
     * Cuts out state out of place name if it's set
     */
    function cutOutState(&$extracted)
    {
        if (!isset($extracted['admin_name1']))
        {
            return;
        }
        $state = $extracted['admin_name1'];
        $start = strpos($extracted['place_name'], $state);
        $end = strlen($state) + $start;
        $extracted['place_name'] = substr($extracted['place_name'], 0, $start) . substr($extracted['place_name'], $end);
    }

    /**
     * @param $val
     * @param $extracted
     *
     * extracts the values as place name
     */
    function extractPlaceName($val, &$extracted)
    {
        if (isset($extracted["place_name"]))
        {
            $extracted["place_name"] = $extracted["place_name"] . " " . $val;
        }
        else
        {
            $extracted["place_name"] = $val;
        }
    }
