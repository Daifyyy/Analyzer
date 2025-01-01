<?php
session_start();
session_destroy();

$folderPath = '.';
$csvFiles = array_filter(glob("Actual/*.csv"), 'is_file');
$historyoneCsvFiles = array_filter(glob("History_One/*.csv"), 'is_file');

// Function to get the last N matches for a given team
define("SEASON", "2024"); // Define the current season
function getLastMatches($matches, $team, $limit, $isHome = null) {
    $filtered = array_filter($matches, function ($match) use ($team, $isHome) {
        $isTeamMatch = $isHome === true ? $match['HomeTeam'] === $team : ($isHome === false ? $match['AwayTeam'] === $team : ($match['HomeTeam'] === $team || $match['AwayTeam'] === $team));
        return $isTeamMatch && $match['Season'] === SEASON;
    });
    usort($filtered, function ($a, $b) {
        return strtotime($b['Date']) - strtotime($a['Date']);
    });
    return array_slice($filtered, 0, $limit);
}

// Function to parse CSV files
function parseCsv($file) {
    $rows = array_map('str_getcsv', file($file));
    $header = array_shift($rows);
    $data = [];
    foreach ($rows as $row) {
        $data[] = array_combine($header, $row);
    }
    return $data;
}

// Poisson probability function
function poisson($lambda, $k) {
    return pow($lambda, $k) * exp(-$lambda) / factorial($k);
}

function factorial($n) {
    return ($n == 0) ? 1 : $n * factorial($n - 1);
}

// Calculate average goals
function calculateAverageGoals($matches, $isHome) {
    $goals = array_map(function ($match) use ($isHome) {
        return $isHome ? $match['FTHG'] : $match['FTAG'];
    }, $matches);
    return array_sum($goals) / count($goals);
}

// Load matches data
$actualSeasonMatches = [];
foreach ($csvFiles as $file) {
    $actualSeasonMatches = array_merge($actualSeasonMatches, parseCsv($file));
}

// Example: Teams to calculate
$homeTeam = 'TeamA';
$awayTeam = 'TeamB';

// Get last 8 matches for both teams
$last8HomeTeam = getLastMatches($actualSeasonMatches, $homeTeam, 8);
$last8AwayTeam = getLastMatches($actualSeasonMatches, $awayTeam, 8);

// Get last 5 home matches for home team
$last5HomeMatches = getLastMatches($actualSeasonMatches, $homeTeam, 5, true);

// Get last 5 away matches for away team
$last5AwayMatches = getLastMatches($actualSeasonMatches, $awayTeam, 5, false);

// Calculate weighted averages
$homeLambda = (0.6 * calculateAverageGoals($last8HomeTeam, true)) + (0.4 * calculateAverageGoals($last5HomeMatches, true));
$awayLambda = (0.6 * calculateAverageGoals($last8AwayTeam, false)) + (0.4 * calculateAverageGoals($last5AwayMatches, false));

// Calculate probabilities
$homeWinProbability = 0;
$drawProbability = 0;
$awayWinProbability = 0;

for ($homeGoals = 0; $homeGoals <= 5; $homeGoals++) {
    for ($awayGoals = 0; $awayGoals <= 5; $awayGoals++) {
        $poissonHome = poisson($homeLambda, $homeGoals);
        $poissonAway = poisson($awayLambda, $awayGoals);
        $matchProbability = $poissonHome * $poissonAway;

        if ($homeGoals > $awayGoals) {
            $homeWinProbability += $matchProbability;
        } elseif ($homeGoals === $awayGoals) {
            $drawProbability += $matchProbability;
        } else {
            $awayWinProbability += $matchProbability;
        }
    }
}

// Output results
$homeWinPercentage = round($homeWinProbability * 100, 2);
$drawPercentage = round($drawProbability * 100, 2);
$awayWinPercentage = round($awayWinProbability * 100, 2);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Football Match Predictor</title>
</head>
<body>
    <div class="container">
        <h1>Football Match Predictor</h1>
        <h3>Final Probabilities</h3>
        <ul>
            <li>Home Win Probability: <?php echo $homeWinPercentage; ?>%</li>
            <li>Draw Probability: <?php echo $drawPercentage; ?>%</li>
            <li>Away Win Probability: <?php echo $awayWinPercentage; ?>%</li>
        </ul>
    </div>
</body>
</html>
