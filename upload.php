<?php
session_start();
include 'includes/db.php';

$uploadDirectory = __DIR__ . '/uploads/';

if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

if (isset($_POST['fontFamily']) && $_POST['fontFamily']) {
    $_SESSION['selectedFont'] = $_POST['fontFamily'];
}


function processPuzzle($uploadedFile, $projectId, $puzzleSizeAcross, $puzzleSizeDown, $option, $caseOption) {
    global $uploadDirectory, $conn;

    if ($uploadedFile['error'] == 0 && pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) == 'txt') {
        $uniqueName = uniqid() . '_' . basename($uploadedFile['name']);
        $targetFile = $uploadDirectory . $uniqueName;

        if (move_uploaded_file($uploadedFile['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("UPDATE projects SET file=:file WHERE id=:id");
            $stmt->bindParam(':file', $uniqueName);
            $stmt->bindParam(':id', $projectId);
            $stmt->execute();

            $words = file($targetFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($words as &$word) {
                if ($caseOption == "uppercase") {
                    $word = strtoupper($word);
                } elseif ($caseOption == "lowercase") {
                    $word = strtolower($word);
                } elseif ($caseOption == "mixed") {
    $splitWord = str_split(strtolower($word)); // Convert the entire word to lowercase
    $randomIndex = rand(0, count($splitWord) - 1); // Randomly select an index
    $splitWord[$randomIndex] = strtoupper($splitWord[$randomIndex]); // Convert the character at the random index to uppercase

    foreach ($splitWord as &$char) {
        if ($char !== strtoupper($char)) { // Check if the character is not already uppercase
            $char = rand(0, 1) ? strtoupper($char) : $char; // Randomly convert to uppercase
        }
    }
    $word = implode('', $splitWord);
}

            }

            $puzzle = generatePuzzle($words, $puzzleSizeAcross, $puzzleSizeDown, $option);
            $solution = generateSolution($words, $puzzleSizeAcross, $puzzleSizeDown);
            $_SESSION['puzzle'] = $puzzle;
            $_SESSION['solution'] = $solution;

            $_SESSION['generation_count'] = ($_SESSION['generation_count'] ?? 0) + 1;
        } else {
            $_SESSION['error'] = "Invalid file or file upload error!";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['project_id'])) {
    $projectId = $_POST['project_id'];
    $puzzleSizeAcross = isset($_POST['sizeAcross']) ? intval($_POST['sizeAcross']) : 10;
    $puzzleSizeDown = isset($_POST['sizeDown']) ? intval($_POST['sizeDown']) : 10;
    $option = isset($_POST['option']) ? intval($_POST['option']) : 3;
    $caseOption = $_POST['case_option'] ?? 'mixed';

    if (isset($_FILES['fileUpload'])) {
        $uploadedFile = $_FILES['fileUpload'];
        processPuzzle($uploadedFile, $projectId, $puzzleSizeAcross, $puzzleSizeDown, $option, $caseOption);
        
    } elseif (isset($_POST['regenerate'])) {
        $stmt = $conn->prepare("SELECT file FROM projects WHERE id=:id");
        $stmt->bindParam(':id', $projectId);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filePath = $uploadDirectory . $row['file'];
            if (file_exists($filePath)) {
                $uploadedFile = ['tmp_name' => $filePath, 'error' => 0, 'name' => $row['file']];
                processPuzzle($uploadedFile, $projectId, $puzzleSizeAcross, $puzzleSizeDown, $option, $caseOption);
            } else {
                $_SESSION['error'] = "The file doesn't exist anymore on the server!";
            }
        } else {
            $_SESSION['error'] = "No previous file to regenerate from!";
        }
    }

    header("Location: project.php?id=" . $projectId);
    exit;
}

function generatePuzzle($words, $gridAcross, $gridDown, $option) {
    $grid = array_fill(0, $gridDown, array_fill(0, $gridAcross, null));

    usort($words, function ($a, $b) {
        return strlen($b) - strlen($a);
    });

    foreach ($words as $word) {
        $placed = false;

        for ($attempt = 0; $attempt < 10 && !$placed; $attempt++) {
            $direction = getValidDirection($grid, $word, $gridAcross, $gridDown);
            if (!$direction) continue;

            $startX = rand(0, $gridAcross - 1 - strlen($word) * abs($direction['x']));
            $startY = rand(0, $gridDown - 1 - strlen($word) * abs($direction['y']));

            $placed = placeWord($grid, $word, $startX, $startY, $direction, $option);
        }
    }

    for ($y = 0; $y < $gridDown; $y++) {
        for ($x = 0; $x < $gridAcross; $x++) {
            if ($grid[$y][$x] === null) {
                $grid[$y][$x] = chr(rand(65, 90));
            }
        }
    }

    return $grid;
}

function generateSolution($words, $gridAcross, $gridDown) {


    $grid = array_fill(0, $gridDown, array_fill(0, $gridAcross, null));

    usort($words, function ($a, $b) {
        return strlen($b) - strlen($a);
    });

    foreach ($words as $word) {
        $placed = false;

        for ($attempt = 0; $attempt < 10 && !$placed; $attempt++) {
            $direction = getValidDirection($grid, $word, $gridAcross, $gridDown);
            if (!$direction) continue;

            $startX = rand(0, $gridAcross - 1 - strlen($word) * abs($direction['x']));
            $startY = rand(0, $gridDown - 1 - strlen($word) * abs($direction['y']));

            $placed = placeWord($grid, $word, $startX, $startY, $direction, 3);
        }
    }

    return $grid;
}


function getValidDirection($grid, $word, $gridAcross, $gridDown) {
    $possibleDirections = [
        ['x' => 0, 'y' => 1],
        ['x' => 1, 'y' => 0],
        ['x' => 1, 'y' => 1],
        ['x' => -1, 'y' => 1],
    ];

    shuffle($possibleDirections);

    foreach ($possibleDirections as $direction) {
        $valid = true;

        for ($y = 0; $y <= $gridDown - strlen($word); $y++) {
            for ($x = 0; $x <= $gridAcross - strlen($word); $x++) {
                $valid = true;
                for ($i = 0; $i < strlen($word); $i++) {
                    $checkX = $x + $i * $direction['x'];
                    $checkY = $y + $i * $direction['y'];
                    if (!isset($grid[$checkY][$checkX]) || ($grid[$checkY][$checkX] !== null && $grid[$checkY][$checkX] !== $word[$i])) {
                        $valid = false;
                        break;
                    }
                }

                if ($valid) {
                    return $direction;
                }
            }
        }
    }

    return null;
}

function placeWord(&$grid, $word, $startX, $startY, $direction, $option) {
    $x = $startX;
    $y = $startY;
    $overlapCount = 0;

    for ($i = 0; $i < strlen($word); $i++) {
        if (!isset($grid[$y][$x]) || ($grid[$y][$x] !== null && $grid[$y][$x] !== $word[$i])) return false;
        if ($grid[$y][$x] === $word[$i]) $overlapCount++;
        $x += $direction['x'];
        $y += $direction['y'];
    }

    if ($option === 2 && $overlapCount === 0) return false;
    if ($option === 3 && $overlapCount !== strlen($word)) return false;

    $x = $startX;
    $y = $startY;

    for ($i = 0; $i < strlen($word); $i++) {
        $grid[$y][$x] = $word[$i];
        $x += $direction['x'];
        $y += $direction['y'];
    }

    return true;
}
?>
