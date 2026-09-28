<?php
/**
 * 로컬 Stable Diffusion WebUI(AUTOMATIC1111) API 연동.
 * 서버는 C:\swbins3\sd-webui 에서 webui-user.bat(--api)로 띄운다.
 */

define('AIHUB_SD_API_BASE', getenv('SD_API_URL') ?: 'http://127.0.0.1:7860');
define('AIHUB_OLLAMA_API_BASE', getenv('OLLAMA_API_URL') ?: 'http://127.0.0.1:11434');
define('AIHUB_TRANSLATE_MODEL', getenv('AIHUB_TRANSLATE_MODEL') ?: 'qwen2.5:7b');

// 생성 실패는 브라우저에 에러 메시지로만 내려가고 서버에는 안 남았다.
// start-all.ps1로 띄우든 serve.ps1을 직접 띄우든 항상 같은 파일에 남도록 error_log 목적지를 고정한다.
define('AIHUB_LOG_FILE', getenv('AIHUB_LOG_FILE') ?: (dirname(__DIR__, 2) . '\\logs\\ai-tools-hub-app.log'));
@mkdir(dirname(AIHUB_LOG_FILE), 0777, true);
ini_set('error_log', AIHUB_LOG_FILE);

/** 로컬 생성 백엔드 연동 실패를 서버 로그(AIHUB_LOG_FILE)에 남긴다. */
function aihubLogError(string $message): void
{
    error_log('[ai-tools-hub] ' . $message);
}

/**
 * sd-webui에 방금 생성 요청을 보냈음을 기록(sd-webui-idle-watchdog.ps1이 이 파일을 보고
 * 유휴 여부를 판단해 체크포인트를 자동 언로드한다).
 */
function aihubMarkSdInUse(): void
{
    $logsDir = dirname(__DIR__, 2) . '\\logs';
    @mkdir($logsDir, 0777, true);
    @file_put_contents($logsDir . '\\sd-last-used.txt', (string)time());
    @unlink($logsDir . '\\sd-unloaded.flag');
}

/**
 * 체크포인트(파일 이름, 확장자 제외) → 라이선스. 2026-09-28 에 라이선스 원문·배포처를 확인한 것만 적는다
 * (COMMERCIAL_SWAP_TODO.md 의 표와 같이 고친다). 여기 없는 체크포인트로 만든 결과물은 commercial_use 가 null 로 나간다.
 */
const AIHUB_CHECKPOINT_LICENSES = [
    'v1-5-pruned-emaonly' => [
        'model' => 'stable-diffusion-v1-5/stable-diffusion-v1-5',
        'license' => 'CreativeML OpenRAIL-M',
        'license_url' => 'https://huggingface.co/stable-diffusion-v1-5/stable-diffusion-v1-5',
        'commercial_use' => true,
        'note' => 'OpenRAIL-M Attachment A 의 금지 용도에는 쓸 수 없다.',
    ],
    'Counterfeit-V3.0_fp16' => [
        'model' => 'gsdf/Counterfeit-V3.0',
        'license' => 'CreativeML OpenRAIL-M (civitai 4468: 생성 이미지 판매 허용)',
        'license_url' => 'https://huggingface.co/gsdf/Counterfeit-V3.0',
        'commercial_use' => true,
        'note' => '모델 파일 자체의 판매·재배포는 안 된다.',
    ],
    'rev_1.2.2-fp16' => [
        'model' => 's6yx/ReV_Animated (1.2.2)',
        'license' => 'CreativeML OpenRAIL-M (civitai 7371: 생성 이미지 판매 허용, 크레딧 표기 필요)',
        'license_url' => 'https://huggingface.co/s6yx/ReV_Animated',
        'commercial_use' => true,
        'note' => '게임 크레딧에 "ReV Animated (s6yx)" 를 적는다.',
    ],
];

const AIHUB_MOTION_MODULE_LICENSE = [
    'model' => 'guoyww/AnimateDiff mm_sd15_v2 (conrevo/AnimateDiff-A1111 변환본)',
    'license' => 'Apache-2.0',
    'license_url' => 'https://huggingface.co/guoyww/animatediff',
    'commercial_use' => true,
];

/**
 * sd-webui 응답의 info(JSON 문자열)에서 실제로 쓰인 체크포인트·시드·프롬프트를 꺼내 출처 기록을 만든다.
 * 음악·음성 서버의 provenance 와 같은 모양이라 허브 화면이 .license.json 으로 내려받게 한다.
 */
function aihubSdProvenance(array $data, string $tool, ?string $motionModule = null): array
{
    $info = json_decode((string)($data['info'] ?? ''), true);
    $info = is_array($info) ? $info : [];
    $checkpoint = (string)($info['sd_model_name'] ?? '');
    $license = AIHUB_CHECKPOINT_LICENSES[$checkpoint] ?? [
        'model' => $checkpoint !== '' ? $checkpoint : 'unknown',
        'license' => 'unknown',
        'license_url' => null,
        'commercial_use' => null,
        'note' => '라이선스를 확인하지 않은 체크포인트다 — 상업용으로 쓰기 전에 COMMERCIAL_SWAP_TODO.md 에 확인 결과를 적는다.',
    ];

    $provenance = ['tool' => $tool] + $license + [
        'checkpoint' => $checkpoint,
        'checkpoint_hash' => $info['sd_model_hash'] ?? null,
        'created_at' => date('c'),
        'params' => [
            'prompt' => $info['prompt'] ?? null,
            'negative_prompt' => $info['negative_prompt'] ?? null,
            'seed' => $info['seed'] ?? null,
            'steps' => $info['steps'] ?? null,
            'sampler' => $info['sampler_name'] ?? null,
            'cfg_scale' => $info['cfg_scale'] ?? null,
            'width' => $info['width'] ?? null,
            'height' => $info['height'] ?? null,
        ],
    ];
    if ($motionModule !== null) {
        $provenance['motion_module'] = ['file' => $motionModule] + AIHUB_MOTION_MODULE_LICENSE;
    }
    return $provenance;
}

function aihubContainsKorean(string $text): bool
{
    return (bool)preg_match('/[\x{AC00}-\x{D7A3}]/u', $text);
}

/**
 * 한국어가 섞인 프롬프트를 로컬 Ollama로 SD/Shap-E용 영어 키워드 프롬프트로 번역.
 * 번역 서버에 문제가 있으면 원문을 그대로 반환한다(생성 자체가 막히지 않도록).
 */
function aihubTranslateToEnglishPrompt(string $text): string
{
    $text = trim($text);
    if ($text === '' || !aihubContainsKorean($text)) {
        return $text;
    }

    $payload = [
        'model' => AIHUB_TRANSLATE_MODEL,
        'prompt' => $text,
        'system' => '너는 이미지·3D 생성 AI용 프롬프트 번역기다. 입력된 한국어 문장을 Stable Diffusion 프롬프트로 쓰기 좋은 ' .
            '간결한 영어 키워드 나열로 번역한다. 설명·따옴표·번역이라는 말 없이 번역 결과 한 줄만 출력한다.',
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return $text;
    }

    $data = json_decode((string)$raw, true);
    $translated = is_array($data) ? trim((string)($data['response'] ?? '')) : '';

    return $translated !== '' ? $translated : $text;
}

/**
 * @return array{ok: bool, images?: string[], error?: string}
 */
function aihubGenerateImage(array $params): array
{
    $payload = [
        'prompt' => aihubTranslateToEnglishPrompt((string)($params['prompt'] ?? '')),
        'negative_prompt' => aihubTranslateToEnglishPrompt((string)($params['negative_prompt'] ?? '')),
        'width' => (int)($params['width'] ?? 512),
        'height' => (int)($params['height'] ?? 512),
        'steps' => (int)($params['steps'] ?? 20),
        'cfg_scale' => 7,
        'sampler_name' => 'Euler a',
        'seed' => (int)($params['seed'] ?? -1),
        'batch_size' => 1,
    ];

    if ($payload['prompt'] === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $refImage = trim((string)($params['init_image'] ?? ''));
    $endpoint = '/sdapi/v1/txt2img';
    if ($refImage !== '') {
        $payload['init_images'] = [$refImage];
        $payload['denoising_strength'] = (float)($params['denoising_strength'] ?? 0.6);
        $endpoint = '/sdapi/v1/img2img';
    }

    aihubMarkSdInUse();
    $ch = curl_init(AIHUB_SD_API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("이미지 생성 연결 실패: {$error}");
        return ['ok' => false, 'error' => "로컬 이미지 생성 서버(sd-webui)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? $data['detail'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("이미지 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (empty($data['images'])) {
        aihubLogError('이미지 생성 응답에 images가 없음(체크포인트 미로딩 가능성)');
        return ['ok' => false, 'error' => '이미지가 생성되지 않았습니다. 체크포인트 모델이 로드되어 있는지 확인해 주세요.'];
    }

    return ['ok' => true, 'images' => $data['images'], 'provenance' => aihubSdProvenance($data, 'sd-webui')];
}

// AI_MODELS_TODO.md 2번이 받는 파일 이름과 같아야 한다. 틀리면 AnimateDiff 가 조용히 꺼져 영상 대신 PNG 한 장이 온다.
define('AIHUB_MOTION_MODULE', getenv('AIHUB_MOTION_MODULE') ?: 'mm_sd15_v2.safetensors');

/**
 * AnimateDiff 확장(extensions/sd-webui-animatediff)을 통한 짧은 영상 클립 생성.
 * @return array{ok: bool, video?: string, error?: string}
 */
function aihubGenerateVideo(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $videoLength = max(8, min(32, (int)($params['video_length'] ?? 16)));

    $payload = [
        'prompt' => aihubTranslateToEnglishPrompt($prompt),
        'negative_prompt' => aihubTranslateToEnglishPrompt((string)($params['negative_prompt'] ?? '')),
        'width' => (int)($params['width'] ?? 512),
        'height' => (int)($params['height'] ?? 512),
        'steps' => (int)($params['steps'] ?? 20),
        'cfg_scale' => 7,
        'sampler_name' => 'Euler a',
        'seed' => -1,
        'batch_size' => 1,
        'alwayson_scripts' => [
            'AnimateDiff' => [
                'args' => [[
                    'model' => AIHUB_MOTION_MODULE,
                    'format' => ['MP4'],
                    'enable' => true,
                    'video_length' => $videoLength,
                    'fps' => (int)($params['fps'] ?? 8),
                    'loop_number' => 0,
                    'closed_loop' => 'R+P',
                    'batch_size' => 16,
                    'stride' => 1,
                    'overlap' => -1,
                ]],
            ],
        ],
    ];

    $refImage = trim((string)($params['init_image'] ?? ''));
    $endpoint = '/sdapi/v1/txt2img';
    if ($refImage !== '') {
        $payload['init_images'] = [$refImage];
        $payload['denoising_strength'] = (float)($params['denoising_strength'] ?? 0.6);
        $endpoint = '/sdapi/v1/img2img';
    }

    aihubMarkSdInUse();
    $ch = curl_init(AIHUB_SD_API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("영상 생성 연결 실패: {$error}");
        return ['ok' => false, 'error' => "로컬 영상 생성 서버(sd-webui)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? $data['detail'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("영상 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (empty($data['images'])) {
        aihubLogError('영상 생성 응답에 images가 없음(체크포인트·모션 모듈 미준비 가능성)');
        return ['ok' => false, 'error' => '영상이 생성되지 않았습니다. 체크포인트·모션 모듈이 준비되어 있는지 확인해 주세요.'];
    }

    // MP4 는 4번째 바이트부터 'ftyp' 로 시작한다. 아니면 AnimateDiff 가 꺼진 채 이미지가 온 것이다.
    if (substr((string)base64_decode(substr($data['images'][0], 0, 16)), 4, 4) !== 'ftyp') {
        aihubLogError('영상 생성 응답이 MP4 가 아님 — 모션 모듈(' . AIHUB_MOTION_MODULE . ') 파일을 AnimateDiff 가 못 찾았을 가능성');
        return ['ok' => false, 'error' => '영상 대신 이미지가 생성됐습니다. 모션 모듈 파일(' . AIHUB_MOTION_MODULE . ')이 sd-webui\extensions\sd-webui-animatediff\model 에 있는지 확인해 주세요.'];
    }

    return ['ok' => true, 'video' => $data['images'][0], 'provenance' => aihubSdProvenance($data, 'sd-webui + AnimateDiff', AIHUB_MOTION_MODULE)];
}

define('AIHUB_MUSIC_API_BASE', getenv('MUSIC_API_URL') ?: 'http://127.0.0.1:7862');

/**
 * 로컬 음악 생성 서버(music-gen/server.py, ACE-Step 1.5 어댑터)를 통한 음악 생성.
 * @return array{ok: bool, audio?: string, provenance?: array, error?: string}
 */
function aihubGenerateMusic(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $duration = max(10, min(240, (int)($params['duration'] ?? 30)));
    // serve.ps1 의 max_execution_time(240초)보다 오래 걸릴 수 있다(첫 요청의 ACE-Step 기동 포함).
    set_time_limit(1900);

    $ch = curl_init(AIHUB_MUSIC_API_BASE . '/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['prompt' => aihubTranslateToEnglishPrompt($prompt), 'duration' => $duration], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        // 첫 요청은 ACE-Step 프로세스 기동·모델 로딩까지 기다리므로 넉넉하게 잡는다.
        CURLOPT_TIMEOUT => 1800,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("음악 생성 연결 실패: {$error}");
        return ['ok' => false, 'error' => "로컬 음악 생성 서버(music-gen)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("음악 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    return ['ok' => true, 'audio' => $data['audio'], 'provenance' => $data['provenance'] ?? null];
}

define('AIHUB_WEBTOON_STYLE', 'webtoon style, clean lineart, cel shading, vibrant flat colors, manhwa panel, ');

/**
 * 이미지 생성과 같은 sd-webui 백엔드에 웹툰/만화 스타일 프리셋을 얹어서 호출.
 * @return array{ok: bool, images?: string[], error?: string}
 */
function aihubGenerateWebtoon(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    return aihubGenerateImage([
        'prompt' => AIHUB_WEBTOON_STYLE . aihubTranslateToEnglishPrompt($prompt),
        'negative_prompt' => trim('photo, realistic, 3d render, ' . aihubTranslateToEnglishPrompt((string)($params['negative_prompt'] ?? ''))),
        'width' => 512,
        'height' => 768,
        'steps' => (int)($params['steps'] ?? 20),
        'init_image' => (string)($params['init_image'] ?? ''),
        'denoising_strength' => (float)($params['denoising_strength'] ?? 0.6),
    ]);
}

define('AIHUB_DESIGN_STYLE', 'flat vector design, clean modern graphic design, minimal, professional branding, ');

/**
 * 이미지 생성과 같은 sd-webui 백엔드에 그래픽 디자인(포스터·로고·배너 등) 프리셋을 얹어서 호출.
 * @return array{ok: bool, images?: string[], error?: string}
 */
function aihubGenerateDesign(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    return aihubGenerateImage([
        'prompt' => AIHUB_DESIGN_STYLE . aihubTranslateToEnglishPrompt($prompt),
        'negative_prompt' => trim('photo, realistic, blurry, watermark, ' . aihubTranslateToEnglishPrompt((string)($params['negative_prompt'] ?? ''))),
        'width' => (int)($params['width'] ?? 512),
        'height' => (int)($params['height'] ?? 512),
        'steps' => (int)($params['steps'] ?? 20),
        'init_image' => (string)($params['init_image'] ?? ''),
        'denoising_strength' => (float)($params['denoising_strength'] ?? 0.6),
    ]);
}

define('AIHUB_ASSET2D_STYLE', 'single 2D game asset icon, sprite, clean vector illustration, centered composition, plain flat background, no shadow, ');

/**
 * 이미지 생성과 같은 sd-webui 백엔드에 2D 게임 에셋(아이템·아이콘·스프라이트) 프리셋을 얹어서 호출.
 * 배경 투명화는 별도 후처리가 필요합니다(플랫 단색 배경으로 생성해 잘라내기 쉽게만 합니다).
 * @return array{ok: bool, images?: string[], error?: string}
 */
function aihubGenerateAsset2D(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    return aihubGenerateImage([
        'prompt' => AIHUB_ASSET2D_STYLE . aihubTranslateToEnglishPrompt($prompt),
        'negative_prompt' => trim('photo, realistic, text, watermark, blurry, cluttered background, multiple objects, ' . aihubTranslateToEnglishPrompt((string)($params['negative_prompt'] ?? ''))),
        'width' => 512,
        'height' => 512,
        'steps' => (int)($params['steps'] ?? 20),
        'init_image' => (string)($params['init_image'] ?? ''),
        'denoising_strength' => (float)($params['denoising_strength'] ?? 0.6),
    ]);
}

define('AIHUB_DOCUMENT_MODEL', getenv('AIHUB_DOCUMENT_MODEL') ?: 'qwen2.5:7b');
define('AIHUB_SEARCH_ENDPOINT', getenv('AIHUB_SEARCH_ENDPOINT') ?: 'https://html.duckduckgo.com/html/');

/** 첨부 텍스트 파일 내용을 프롬프트 앞에 참고자료로 붙인다(너무 길면 일부만 사용). */
function aihubWithAttachment(string $prompt, string $attachment): string
{
    $attachment = trim($attachment);
    if ($attachment === '') {
        return $prompt;
    }
    if (mb_strlen($attachment) > 8000) {
        $attachment = mb_substr($attachment, 0, 8000) . "\n…(이하 생략)";
    }
    return "다음은 참고 자료다:\n\n{$attachment}\n\n---\n\n위 자료를 참고해서 아래 요청에 답해줘:\n{$prompt}";
}

/**
 * 무료 웹 검색(DuckDuckGo HTML, API 키 불필요). 상위 N개 결과의 제목+요약+링크만 반환.
 * 검색 엔진 도메인이 방화벽에 막혀 있으면(회사 네트워크 등) 연결 실패로 처리된다.
 * @return array{ok: bool, results?: array<int, array{title: string, snippet: string, url: string}>, error?: string}
 */
function aihubWebSearch(string $query, int $limit = 5): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => '검색어가 없습니다.'];
    }

    $ch = curl_init(AIHUB_SEARCH_ENDPOINT . '?' . http_build_query(['q' => $query]));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ]);
    $html = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("웹 검색 연결 실패: {$error}");
        return ['ok' => false, 'error' => "웹 검색 서비스에 연결할 수 없습니다: {$error}"];
    }

    $results = [];
    if (preg_match_all(
        '#<a[^>]*class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)</a>.*?<a[^>]*class="result__snippet"[^>]*>(.*?)</a>#is',
        (string)$html,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $m) {
            if (count($results) >= $limit) {
                break;
            }
            $results[] = [
                'url' => html_entity_decode(strip_tags($m[1]), ENT_QUOTES),
                'title' => trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES)),
                'snippet' => trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES)),
            ];
        }
    }

    if (empty($results)) {
        return ['ok' => false, 'error' => '검색 결과를 찾지 못했습니다(검색어를 바꿔보거나, 검색 사이트 구조가 바뀌었을 수 있습니다).'];
    }

    return ['ok' => true, 'results' => $results];
}

/** 검색 결과를 LLM 프롬프트에 넣기 좋은 텍스트 블록으로 정리. */
function aihubFormatSearchResults(array $results): string
{
    $lines = [];
    foreach ($results as $i => $r) {
        $lines[] = ($i + 1) . ". {$r['title']}\n{$r['snippet']}\n출처: {$r['url']}";
    }
    return implode("\n\n", $lines);
}

/**
 * 로컬 Ollama로 문서(글) 생성. web_search가 true면 프롬프트로 먼저 무료 웹 검색을 해서 결과를 참고자료로 넣는다.
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateDocument(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $context = (string)($params['attachment'] ?? '');
    if (!empty($params['web_search'])) {
        $search = aihubWebSearch($prompt);
        if ($search['ok']) {
            $searchBlock = "[아래는 방금 웹 검색으로 찾은 최신 정보다. 답변에 반영하고, 필요하면 출처를 언급해라]\n\n" .
                aihubFormatSearchResults($search['results']);
            $context = trim($searchBlock . "\n\n" . $context);
        } else {
            $context = trim("[웹 검색을 시도했지만 실패했다: {$search['error']}. 알고 있는 지식으로만 답해라]\n\n" . $context);
        }
    }

    $payload = [
        'model' => AIHUB_DOCUMENT_MODEL,
        'prompt' => aihubWithAttachment($prompt, $context),
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("문서/SNS 생성 연결 실패(Ollama): {$error}");
        return ['ok' => false, 'error' => "로컬 문서 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("문서/SNS 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        aihubLogError("문서/SNS 생성 실패(Ollama 응답 error): {$data['error']}");
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}

define('AIHUB_SOCIAL_PROMPTS', [
    'instagram' => '너는 SNS 마케터다. 인스타그램 게시물용 감성적인 캡션과 어울리는 해시태그 10개를 작성한다. 캡션과 해시태그만 출력한다.',
    'tiktok' => '너는 숏폼 콘텐츠 작가다. 틱톡/릴스용 15~30초 분량 스크립트(훅-본문-CTA 구조)와 추천 해시태그 5개를 작성한다.',
    'blog' => '너는 블로그 작가다. SEO를 고려한 블로그 포스트 초안을 소제목 구조로 작성한다(서론-본문 소제목 2~3개-결론).',
    'cafe' => '너는 온라인 커뮤니티(네이버 카페 등) 운영자다. 친근한 말투의 카페 게시글 초안을 작성한다.',
]);

/**
 * 로컬 Ollama로 SNS/블로그 콘텐츠 초안 생성(실제 게시·업로드는 하지 않음).
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateSocial(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $platform = (string)($params['platform'] ?? 'instagram');
    $system = AIHUB_SOCIAL_PROMPTS[$platform] ?? AIHUB_SOCIAL_PROMPTS['instagram'];

    $payload = [
        'model' => AIHUB_DOCUMENT_MODEL,
        'prompt' => aihubWithAttachment($prompt, (string)($params['attachment'] ?? '')),
        'system' => $system,
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("문서/SNS 생성 연결 실패(Ollama): {$error}");
        return ['ok' => false, 'error' => "로컬 문서 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("문서/SNS 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        aihubLogError("문서/SNS 생성 실패(Ollama 응답 error): {$data['error']}");
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}

define('AIHUB_VOICE_API_BASE', getenv('VOICE_API_URL') ?: 'http://127.0.0.1:7863');

/**
 * 로컬 Supertonic 3 서버(voice-gen/server.py)를 통한 음성 생성.
 * @return array{ok: bool, audio?: string, provenance?: array, error?: string}
 */
function aihubGenerateVoice(array $params): array
{
    $text = trim((string)($params['text'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'error' => '텍스트를 입력해 주세요.'];
    }

    $payload = [
        'text' => $text,
        'language' => (string)($params['language'] ?? 'ko'),
        'voice' => (string)($params['voice'] ?? 'F1'),
    ];

    $ch = curl_init(AIHUB_VOICE_API_BASE . '/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("음성 생성 연결 실패: {$error}");
        return ['ok' => false, 'error' => "로컬 음성 생성 서버(voice-gen)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("음성 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => $detail !== '' ? $detail : "생성 실패 (HTTP {$status})"];
    }

    return ['ok' => true, 'audio' => $data['audio'], 'provenance' => $data['provenance'] ?? null];
}

define('AIHUB_3D_API_BASE', getenv('AIHUB_3D_API_URL') ?: 'http://127.0.0.1:7864');

/**
 * 로컬 Shap-E 서버(3d-gen/server.py)를 통한 텍스트→3D 에셋 생성.
 * @return array{ok: bool, model?: string, error?: string}
 */
function aihubGenerate3D(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    $refImage = trim((string)($params['image'] ?? ''));
    if ($prompt === '' && $refImage === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력하거나 참고 이미지를 첨부해 주세요.'];
    }

    $payload = [
        'prompt' => aihubTranslateToEnglishPrompt($prompt),
        'steps' => (int)($params['steps'] ?? 64),
        'guidance_scale' => (float)($params['guidance_scale'] ?? 15.0),
    ];
    if ($refImage !== '') {
        $payload['image'] = $refImage;
    }

    $ch = curl_init(AIHUB_3D_API_BASE . '/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("3D 생성 연결 실패: {$error}");
        return ['ok' => false, 'error' => "로컬 3D 생성 서버(3d-gen)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("3D 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => $detail !== '' ? $detail : "생성 실패 (HTTP {$status})"];
    }

    return ['ok' => true, 'model' => $data['model'], 'provenance' => $data['provenance'] ?? null];
}

define('AIHUB_CODE_MODEL', getenv('AIHUB_CODE_MODEL') ?: 'qwen2.5-coder:7b');

/**
 * 로컬 Ollama(코딩 특화 모델)로 코드/앱 생성.
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateCode(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $language = trim((string)($params['language'] ?? ''));
    $languageInstruction = $language !== '' ? " 반드시 {$language}로 작성한다." : '';

    $payload = [
        'model' => AIHUB_CODE_MODEL,
        'prompt' => aihubWithAttachment($prompt, (string)($params['attachment'] ?? '')),
        'system' => '너는 코딩 도우미다. 요청받은 앱·코드를 바로 실행 가능한 완결된 형태로, 코드 블록과 짧은 설명만으로 답한다.' . $languageInstruction,
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("코드/UI 생성 연결 실패(Ollama): {$error}");
        return ['ok' => false, 'error' => "로컬 코드 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("코드/UI 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        aihubLogError("코드/UI 생성 실패(Ollama 응답 error): {$data['error']}");
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}

/**
 * 로컬 Ollama(qwen2.5-coder)로 실행 가능한 단일 HTML UI 목업 생성(미리보기용).
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateUI(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $payload = [
        'model' => AIHUB_CODE_MODEL,
        'prompt' => aihubWithAttachment($prompt, (string)($params['attachment'] ?? '')),
        'system' => '너는 UI/UX 디자이너 겸 프론트엔드 개발자다. 요청받은 화면을 모던하고 세련된 디자인으로, ' .
            '인라인 CSS(+필요하면 JS)를 포함한 완전히 동작하는 단일 HTML 파일로 작성한다. ' .
            '외부 이미지·폰트 CDN 없이 시스템 폰트와 CSS만으로 꾸민다. ```html 코드 블록 하나만 출력하고 다른 설명은 하지 않는다.',
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        aihubLogError("코드/UI 생성 연결 실패(Ollama): {$error}");
        return ['ok' => false, 'error' => "로컬 코드 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        aihubLogError("코드/UI 생성 실패 (HTTP {$status}): {$detail}");
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        aihubLogError("코드/UI 생성 실패(Ollama 응답 error): {$data['error']}");
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}

/**
 * sd-webui의 진행률 API를 그대로 프록시(생성 요청과 별도 커넥션이라 폴링 가능).
 * @return array{ok: bool, progress?: float, eta_relative?: float}
 */
function aihubSdProgress(): array
{
    $ch = curl_init(AIHUB_SD_API_BASE . '/sdapi/v1/progress?skip_current_image=true');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        return ['ok' => false];
    }

    return [
        'ok' => true,
        'progress' => (float)($data['progress'] ?? 0),
        'eta_relative' => (float)($data['eta_relative'] ?? 0),
    ];
}

/** 짧은 타임아웃으로 한 서비스의 응답 여부만 확인 (성공/실패만 필요, 응답 내용은 버림). */
function aihubPing(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    return $errno === 0;
}

/**
 * 로컬 AI 생성 백엔드 4개의 가동 여부를 확인.
 * @return array<int, array{name: string, ok: bool}>
 */
function aihubCheckStatus(): array
{
    return [
        ['name' => '이미지·동영상 (sd-webui, 7860)', 'ok' => aihubPing(AIHUB_SD_API_BASE . '/sdapi/v1/options')],
        ['name' => '음악 (music-gen, 7862)', 'ok' => aihubPing(AIHUB_MUSIC_API_BASE . '/health')],
        ['name' => '음성 (voice-gen, 7863)', 'ok' => aihubPing(AIHUB_VOICE_API_BASE . '/health')],
        ['name' => '문서·코드 (Ollama, 11434)', 'ok' => aihubPing(AIHUB_OLLAMA_API_BASE . '/api/tags')],
        ['name' => '3D 에셋 (3d-gen, 7864)', 'ok' => aihubPing(AIHUB_3D_API_BASE . '/health')],
    ];
}
