<?php
/**
 * Common Helper Functions
 * Smart Health & Diet Recommendation System
 */

// Start session if not already started and headers not yet sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Sanitize and escape output strings to prevent XSS.
 *
 * @param mixed $data
 * @return string
 */
function e($data): string {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function requestString($value): string {
    return is_string($value) ? $value : '';
}

function isValidCsrfToken($token): bool {
    return isset($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isValidDate($value): bool {
    if (!is_string($value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function renderBackButton(string $fallback = '#'): void {
    echo '<a class="page-back-button" href="' . e($fallback) . '" onclick="if (window.history.length > 1) { window.history.back(); return false; }" aria-label="Back">'
        . '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>'
        . '<span>Back</span></a>';
}

/**
 * Set a flash message for the next request.
 *
 * @param string $type 'success' | 'error' | 'warning' | 'info'
 * @param string $message
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the current flash message.
 *
 * @return array|null
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message HTML if present.
 * Uses the exact design styling and alerts matching the dark theme.
 */
function renderFlashMessage(): void {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = e($flash['message']);
        
        $borderColor = '#2ecc71';
        $textColor = '#2ecc71';
        $bgColor = 'rgba(46, 204, 113, 0.12)';
        
        if ($type === 'error') {
            $borderColor = '#e74c3c';
            $textColor = '#e74c3c';
            $bgColor = 'rgba(231, 76, 60, 0.12)';
        } elseif ($type === 'warning') {
            $borderColor = '#f39c12';
            $textColor = '#f39c12';
            $bgColor = 'rgba(243, 156, 18, 0.12)';
        }
        
        echo "<div style=\"background-color: {$bgColor}; border: 1px solid {$borderColor}; color: {$textColor}; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; display: flex; align-items: center; justify-content: space-between;\">
                <span>{$message}</span>
                <button type=\"button\" onclick=\"this.parentElement.remove();\" style=\"background:none; border:none; color:inherit; font-size:18px; cursor:pointer; line-height:1;\">&times;</button>
              </div>";
    }
}

/**
 * Calculate BMI given weight in kg and height in cm.
 *
 * @param float $weightKg
 * @param float $heightCm
 * @return array ['bmi' => float, 'category' => string, 'color' => string]
 */
function calculateBMI(float $weightKg, float $heightCm): array {
    if ($heightCm <= 0 || $weightKg <= 0) {
        return ['bmi' => 0, 'category' => 'Unknown', 'color' => '#8c909a'];
    }
    
    $heightM = $heightCm / 100.0;
    $bmi = round($weightKg / ($heightM * $heightM), 1);
    
    if ($bmi < 18.5) {
        $category = 'Underweight';
        $color = '#3498db'; // blue
    } elseif ($bmi < 25.0) {
        $category = 'Normal';
        $color = '#2ecc71'; // green
    } elseif ($bmi < 30.0) {
        $category = 'Overweight';
        $color = '#f39c12'; // orange/amber
    } else {
        $category = 'Obese';
        $color = '#e74c3c'; // red
    }
    
    return [
        'bmi' => $bmi,
        'category' => $category,
        'color' => $color
    ];
}

/**
 * Estimate daily calorie needs using Mifflin-St Jeor equation.
 *
 * @param float $weightKg
 * @param float $heightCm
 * @param int $age
 * @param string $gender 'male' | 'female'
 * @param string $activityLevel
 * @param string $goal
 * @return int estimated daily calories
 */
function calculateDailyCalorieTarget(float $weightKg, float $heightCm, int $age, string $gender, string $activityLevel, string $goal): int {
    if ($weightKg <= 0 || $heightCm <= 0 || $age <= 0) {
        return 2000;
    }
    
    // BMR formula (Mifflin-St Jeor)
    if (strtolower($gender) === 'female') {
        $bmr = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) - 161;
    } else {
        $bmr = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) + 5;
    }
    
    // Activity multiplier
    $multipliers = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'very_active' => 1.725,
        'extra_active' => 1.9,
    ];
    $multiplier = $multipliers[$activityLevel] ?? 1.2;
    $tdee = $bmr * $multiplier;
    
    // Goal adjustment
    switch ($goal) {
        case 'weight_loss':
            $target = $tdee - 400; // safe deficit
            break;
        case 'weight_gain':
            $target = $tdee + 400; // safe surplus
            break;
        case 'maintain_weight':
        case 'general_fitness':
        default:
            $target = $tdee;
            break;
    }
    
    return max(1200, (int)round($target));
}

/**
 * Return relative root path based on caller nesting.
 *
 * @return string
 */
function getBasePath(): string {
    // If inside /admin/, /dietitian/, or /user/ subfolders, base path is ../
    $currentDir = dirname($_SERVER['PHP_SELF'] ?? '');
    if (str_ends_with($currentDir, '/admin') || str_ends_with($currentDir, '/dietitian') || str_ends_with($currentDir, '/user')) {
        return '../';
    }
    return '';
}
