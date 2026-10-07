<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include("conn.php");

if (!$conn) {
    echo json_encode(["code" => 1, "msg" => "Database Connection Error", "data" => null]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

// 1. Fetch Global Styling & Fonts
$st_res = mysqli_query($conn, "SELECT * FROM guide_settings WHERE id = 1");
$settings = mysqli_fetch_assoc($st_res) ?: ['bg_color' => '#ffffff', 'text_color' => '#333333', 'font_family' => 'Inter', 'font_size' => '16px'];

$bg_color    = $settings['bg_color'];
$text_color  = $settings['text_color'];
$font_family = $settings['font_family'];
$font_size   = $settings['font_size'];

// Google Font Loader Injector
$font_url_param = str_replace(' ', '+', $font_family);
$google_font_css = "<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family={$font_url_param}:wght@400;600;700&display=swap\">";

// 2. Fetch Dynamic Content
$query = "SELECT * FROM beginner_guide_dynamic ORDER BY sort_order ASC, id ASC";
$result = mysqli_query($conn, $query);

$h2_style = "font-family: '{$font_family}', sans-serif; color: {$text_color}; font-size: {$font_size}; margin-bottom: 6px;";

$html_content = $google_font_css;
$html_content .= "<div style=\"background-color: {$bg_color}; padding: 12px; font-family: '{$font_family}', sans-serif; color: {$text_color};\">";

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $title = htmlspecialchars($row['guide_title']);
        $text_lines = explode("\n", $row['guide_text']);
        $img_align = $row['img_align'] ?? 'block';
        
        $html_content .= "<h2 style=\"{$h2_style}\">{$title}</h2>";
        
        foreach ($text_lines as $line) {
            $line_trim = htmlspecialchars(trim($line));
            if (!empty($line_trim)) {
                $html_content .= "<h2 style=\"{$h2_style}\">{$line_trim}</h2>";
            }
        }
        
        // Dynamic Alignment Rendering
        if (!empty($row['image_url'])) {
            $images = explode(',', $row['image_url']);
            
            if ($img_align === 'inline') {
                // Side-by-Side Alignment
                $html_content .= "<div style=\"display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; margin-bottom: 12px;\">";
                foreach ($images as $img_src) {
                    $trimmed_url = htmlspecialchars(trim($img_src));
                    if (!empty($trimmed_url)) {
                        $html_content .= "<img src=\"{$trimmed_url}\" style=\"max-width: 48%; height: auto; border-radius: 6px;\">";
                    }
                }
                $html_content .= "</div>";
            } else {
                // Vertical Alignment (One under another)
                $html_content .= "<h2 style=\"{$h2_style}\">";
                foreach ($images as $img_src) {
                    $trimmed_url = htmlspecialchars(trim($img_src));
                    if (!empty($trimmed_url)) {
                        $html_content .= "<img src=\"{$trimmed_url}\" style=\"width: 100%; max-width: 450px; margin-bottom: 6px;\">";
                    }
                }
                $html_content .= "</h2>";
            }
        }
        
        $html_content .= "<p><br></p>";
    }
}

$html_content .= "</div>";

echo json_encode([
    "data" => [
        "playingGuide" => $html_content
    ],
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "traceId" => "",
    "serviceNowTime" => date("Y-m-d H:i:s")
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>