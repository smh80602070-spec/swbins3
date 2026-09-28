<?php
/**
 * 메인 페이지 HTML 렌더링. Slim 라우트에서 호출.
 */

require_once __DIR__ . '/data.php';

function aihubRenderHome(array $tips): string
{
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI 생성 스튜디오 · swbins3</title>
<script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer/dist/model-viewer.min.js"></script>
<style>
  * { box-sizing: border-box; }
  :root {
    --bg: #ffffff; --panel-bg: #fafafa; --card: #ffffff; --border: #e4e4e7;
    --text: #18181b; --muted: #71717a; --faint: #a1a1aa;
    --accent: #4f46e5; --accent-ink: #ffffff; --accent-soft: #eef2ff;
    --hover-bg: #f4f4f5; --up: #16a34a; --down: #dc2626;
    --code-bg: #18181b; --code-text: #e4e4e7;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0b0b0d; --panel-bg: #111114; --card: #17171b; --border: #27272a;
      --text: #f4f4f5; --muted: #a1a1aa; --faint: #71717a;
      --accent: #818cf8; --accent-ink: #101014; --accent-soft: #1e1b4b;
      --hover-bg: #1c1c20; --up: #4ade80; --down: #f87171;
      --code-bg: #000000; --code-text: #d4d4d8;
    }
  }
  html, body { margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text);
         font-family: -apple-system, "Segoe UI", "Pretendard", "Malgun Gothic", sans-serif; }
  a { color: var(--accent); }

  .shell { display: flex; max-width: 1120px; margin: 0 auto; min-height: 100vh; align-items: stretch; }

  .sidebar { flex: 0 0 216px; padding: 28px 14px; border-right: 1px solid var(--border);
             background: var(--panel-bg); }
  .brand { font-size: 15px; font-weight: 800; letter-spacing: -.01em; padding: 0 10px; margin-bottom: 22px; }
  .brand small { display: block; font-size: 11px; font-weight: 500; color: var(--muted); margin-top: 3px; }
  .nav-section-label { font-size: 11px; font-weight: 700; color: var(--faint); text-transform: uppercase;
                        letter-spacing: .06em; padding: 0 10px; margin: 18px 0 6px; }
  .nav-section-label:first-of-type { margin-top: 0; }
  .nav-list { display: flex; flex-direction: column; gap: 1px; }
  .nav-item, .type-btn { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px;
              font-size: 13.5px; font-weight: 600; color: var(--muted); cursor: pointer; user-select: none;
              border: none; background: transparent; text-align: left; }
  .nav-item .ico, .type-btn .ico { font-size: 15px; line-height: 1; flex: 0 0 auto; }
  .nav-item:hover, .type-btn:hover { background: var(--hover-bg); color: var(--text); }
  .nav-item.active, .type-btn.active { background: var(--accent-soft); color: var(--accent); }

  .content { flex: 1 1 auto; padding: 28px 32px; max-width: 720px; }
  .panel { display: none; }
  .panel.active { display: block; }
  .page-title { font-size: 15px; font-weight: 800; margin: 0 0 4px; }
  .page-sub { font-size: 12.5px; color: var(--muted); margin: 0 0 18px; }

  .variant-picker { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
  .variant-btn { padding: 6px 13px; border-radius: 999px; border: 1px solid var(--border); background: var(--card);
                 font-size: 12.5px; font-weight: 700; color: var(--muted); cursor: pointer; }
  .variant-btn:hover { border-color: var(--accent); color: var(--text); }
  .variant-btn.active { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }

  .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px;
          padding: 22px 24px; }
  .card label { display: block; font-size: 12.5px; font-weight: 700; color: var(--muted); margin: 14px 0 6px; }
  .card label:first-child { margin-top: 0; }
  .card textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;
                    font-family: inherit; font-size: 13.5px; resize: vertical; color: var(--text); background: var(--bg); }
  .card textarea:focus, .opt-row select:focus, .opt-row input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
  .opt-row { display: flex; gap: 12px; align-items: flex-end; margin-top: 14px; flex-wrap: wrap; }
  .opt-row label { display: flex; flex-direction: column; gap: 5px; font-size: 12px; font-weight: 700;
                    color: var(--muted); margin: 0; }
  .opt-row select, .opt-row input[type=number] { padding: 7px 10px; border: 1px solid var(--border);
                    border-radius: 7px; font-family: inherit; font-size: 13px; background: var(--bg); color: var(--text); }
  .opt-row input[type=number] { width: 64px; }
  .opt-row input[type=file] { font-size: 12px; max-width: 200px; color: var(--muted); }
  #gen-submit { padding: 10px 22px; border: none; border-radius: 8px; background: var(--accent); color: var(--accent-ink);
                font-weight: 700; font-size: 13.5px; cursor: pointer; margin-left: auto; }
  #gen-submit:hover { opacity: .92; }
  #gen-submit:disabled { opacity: .5; cursor: not-allowed; }
  .gen-status { margin-top: 14px; font-size: 13px; }
  .gen-status.error { color: var(--down); }
  .gen-status.loading { color: var(--muted); }
  .gen-progress { margin-top: 8px; height: 6px; border-radius: 999px; background: var(--panel-bg);
                   border: 1px solid var(--border); overflow: hidden; display: none; }
  .gen-progress-bar { height: 100%; width: 0%; background: var(--accent); transition: width .25s ease; }
  .gen-result { margin-top: 16px; }
  .gen-result img, .gen-result video { max-width: 100%; border-radius: 10px; border: 1px solid var(--border); display: block; }
  .gen-result audio { width: 100%; }
  .gen-result .doc-text { white-space: pre-wrap; font-size: 13.5px; line-height: 1.65; background: var(--panel-bg);
                           border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px; }
  .gen-result .code-text { white-space: pre-wrap; font-family: "Consolas", "D2Coding", monospace; font-size: 13px;
                            line-height: 1.6; background: var(--code-bg); color: var(--code-text); border-radius: 10px;
                            padding: 16px 18px; overflow-x: auto; }
  .gen-result model-viewer { width: 100%; height: 360px; background: var(--panel-bg); border-radius: 10px;
                              border: 1px solid var(--border); }
  .gen-result .model-download { display: inline-block; margin-top: 10px; font-size: 13px; font-weight: 700; color: var(--accent); }
  .hint { font-size: 12px; color: var(--faint); margin: 16px 0 0; line-height: 1.6; }

  .status-list { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
  .status-row { display: flex; align-items: center; gap: 10px; font-size: 13.5px; padding: 10px 14px;
                 border: 1px solid var(--border); border-radius: 8px; background: var(--panel-bg); }
  .status-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; background: var(--faint); }
  .status-dot.up { background: var(--up); }
  .status-dot.down { background: var(--down); }
  .status-row .status-label { flex: 1 1 auto; }
  .status-row .status-text { font-size: 12px; font-weight: 700; }
  .status-row .status-text.up { color: var(--up); }
  .status-row .status-text.down { color: var(--down); }
  #status-refresh { padding: 9px 18px; border: none; border-radius: 8px; background: var(--accent); color: var(--accent-ink);
                     font-weight: 700; font-size: 13px; cursor: pointer; }

  .tips h2 { font-size: .78rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em;
             margin: 22px 0 10px; }
  .tips h2:first-child { margin-top: 0; }
  .tip { background: var(--card); border: 1px solid var(--border); border-radius: 10px;
         padding: 13px 16px; margin-bottom: 10px; }
  .tip b { display: block; font-size: 13.5px; margin-bottom: 4px; }
  .tip span { font-size: 13px; color: var(--muted); line-height: 1.5; }

  @media (max-width: 760px) {
    .shell { flex-direction: column; }
    .sidebar { flex: none; width: 100%; border-right: none; border-bottom: 1px solid var(--border);
               padding: 14px 12px; }
    .brand { margin-bottom: 12px; }
    .nav-section-label { display: none; }
    .nav-list { flex-direction: row; overflow-x: auto; gap: 6px; padding-bottom: 2px; }
    .nav-item, .type-btn { flex: 0 0 auto; white-space: nowrap; }
    .content { padding: 20px 16px 40px; max-width: none; }
  }
</style>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand">✨ AI 생성 스튜디오<small>로컬 전용, 외부 전송 없음</small></div>

    <div class="nav-section-label">생성</div>
    <div class="nav-list" id="type-picker"></div>

    <div class="nav-section-label">도구</div>
    <div class="nav-list">
      <div class="nav-item tab" data-tab="status"><span class="ico">🩺</span>서버 상태</div>
      <div class="nav-item tab" data-tab="token-saving"><span class="ico">💡</span>토큰 절약법</div>
    </div>
  </aside>

  <main class="content">
  <div class="panel active" id="panel-gen">
    <p class="page-title" id="gen-title">이미지 생성</p>
    <p class="page-sub">이 PC에 설치된 로컬 AI로 바로 생성합니다.</p>

    <div class="variant-picker" id="variant-picker"></div>

    <div class="card">
      <label id="prompt-label" for="gen-prompt">프롬프트</label>
      <textarea id="gen-prompt" rows="3"></textarea>

      <label id="negative-label" for="gen-negative">네거티브 프롬프트 (선택, 빼고 싶은 요소)</label>
      <textarea id="gen-negative" rows="2" placeholder="예: blurry, low quality, watermark"></textarea>

      <div class="opt-row">
        <label data-opt="size">크기
          <select id="opt-size">
            <option value="512x512" selected>512×512</option>
            <option value="768x512">768×512 (가로형)</option>
            <option value="512x768">512×768 (세로형)</option>
          </select>
        </label>
        <label data-opt="videosize">크기
          <select id="opt-videosize">
            <option value="384" selected>384×384 (빠름)</option>
            <option value="448">448×448</option>
            <option value="512">512×512 (느림)</option>
          </select>
        </label>
        <label data-opt="steps">스텝<input type="number" id="opt-steps" value="20" min="1" max="128"></label>
        <label data-opt="length">프레임 수<input type="number" id="opt-length" value="16" min="8" max="32"></label>
        <label data-opt="fps">FPS<input type="number" id="opt-fps" value="8" min="4" max="16"></label>
        <label data-opt="duration">길이(초)<input type="number" id="opt-duration" value="30" min="10" max="240"></label>
        <label data-opt="guidance">반영도<input type="number" id="opt-guidance" value="15" min="1" max="30"></label>
        <label data-opt="language">언어
          <select id="opt-language">
            <option value="ko" selected>한국어</option>
            <option value="en">영어</option>
            <option value="ja">일본어</option>
          </select>
        </label>
        <label data-opt="voice">목소리
          <select id="opt-voice">
            <option value="F1" selected>여성 1</option>
            <option value="F2">여성 2</option>
            <option value="F3">여성 3</option>
            <option value="F4">여성 4</option>
            <option value="F5">여성 5</option>
            <option value="M1">남성 1</option>
            <option value="M2">남성 2</option>
            <option value="M3">남성 3</option>
            <option value="M4">남성 4</option>
            <option value="M5">남성 5</option>
          </select>
        </label>
        <label data-opt="codelang">개발 언어
          <select id="opt-codelang">
            <option value="" selected>자동 선택</option>
            <option value="HTML/CSS/JavaScript">HTML/CSS/JavaScript</option>
            <option value="Python">Python</option>
            <option value="PHP">PHP</option>
            <option value="Java">Java</option>
            <option value="C#">C#</option>
            <option value="C++">C++</option>
            <option value="Go">Go</option>
            <option value="TypeScript">TypeScript</option>
            <option value="Swift">Swift</option>
            <option value="Kotlin">Kotlin</option>
          </select>
        </label>
        <label data-opt="websearch" style="flex-direction: row; align-items: center; gap: 6px;">
          <input type="checkbox" id="opt-websearch"> 웹 검색 사용(무료, DuckDuckGo)
        </label>
        <label id="file-label" for="gen-file">첨부파일(선택)<input type="file" id="gen-file"></label>
        <button id="gen-submit" type="button">생성</button>
      </div>

      <div class="gen-status" id="gen-status" hidden></div>
      <div class="gen-progress" id="gen-progress"><div class="gen-progress-bar" id="gen-progress-bar"></div></div>
      <div class="gen-result" id="gen-result"></div>
      <p class="hint" id="gen-hint"></p>
    </div>
  </div>

  <div class="panel" id="panel-status">
    <p class="page-title">서버 상태</p>
    <p class="page-sub">로컬 생성 백엔드가 켜져 있는지 확인합니다.</p>
    <div class="card">
      <div class="opt-row" style="margin-top:0">
        <button id="status-refresh" type="button">상태 확인</button>
      </div>
      <div id="status-list" class="status-list"></div>
      <p class="hint">
        생성이 안 될 때 여기서 어떤 서버가 꺼져 있는지 먼저 확인하세요.
        전체를 한 번에 켜려면 <code>C:\swbins3\start-all.bat</code>을 실행하면 됩니다(처음엔 모델 로딩으로 1~2분 걸릴 수 있습니다).
        끄려면 <code>stop-all.bat</code>을 실행하세요.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-token-saving">
    <p class="page-title">토큰 절약법</p>
    <p class="page-sub">AI 비용·토큰을 아끼는 팁 모음.</p>
    <div class="tips">
<?php foreach ($tips as $group) { ?>
      <h2><?php echo aihubEsc($group['label'] ?? ''); ?></h2>
<?php foreach ($group['tips'] as $tip) { ?>
      <div class="tip">
        <b><?php echo aihubEsc($tip['title']); ?></b>
        <span><?php echo aihubEsc($tip['detail']); ?></span>
      </div>
<?php } ?>
<?php } ?>
    </div>
  </div>
  </main>
</div>
<script>
// 타입마다 "종류"(variant)를 두어 비슷한 백엔드를 쓰는 항목을 하나의 사이드바 메뉴로 묶는다.
var GEN_TYPES = {
  image: {
    icon: '🖼️', label: '이미지', hasNegative: true, resultKind: 'image',
    file: { label: '참고 이미지(선택)', accept: 'image/*', mode: 'base64', field: 'init_image' },
    promptLabel: '프롬프트 (영어일수록 결과가 좋습니다)',
    variants: {
      general: { label: '일반', endpoint: '/generate/image', opts: ['size', 'steps'], sdProgress: true,
                 placeholder: '예: a cozy cabin in a snowy forest, warm lighting, digital painting',
                 hint: '로컬 Stable Diffusion(sd-webui)으로 생성합니다. 참고 이미지를 첨부하면 그 이미지를 바탕으로 변형합니다(img2img).' },
      webtoon: { label: '웹툰/만화', endpoint: '/generate/webtoon', opts: ['steps'], sdProgress: true,
                 placeholder: '예: a girl looking at the sunset, school rooftop',
                 hint: '웹툰/만화 스타일 프리셋(세로 컷, 클린 라인아트)을 적용합니다.' },
      design: { label: '디자인(로고·포스터)', endpoint: '/generate/design', opts: ['size', 'steps'], sdProgress: true,
                placeholder: '예: minimalist logo for a coffee shop, letter M, line art',
                hint: '플랫 디자인/로고·포스터 프리셋을 적용합니다. 텍스트 렌더링은 정확하지 않을 수 있습니다.' },
      asset2d: { label: '2D 게임 에셋', endpoint: '/generate/asset2d', opts: ['steps'], sdProgress: true,
                 placeholder: '예: healing potion bottle icon, fantasy RPG item',
                 hint: '2D 게임 아이콘/스프라이트 프리셋(단색 배경, 중앙 정렬)을 적용합니다. Godot·Unity엔 배경 제거가 별도로 필요합니다.' }
    }
  },
  video: {
    icon: '🎬', label: '동영상', hasNegative: true, resultKind: 'video',
    file: { label: '참고 이미지(선택)', accept: 'image/*', mode: 'base64', field: 'init_image' },
    promptLabel: '프롬프트 (영어일수록 결과가 좋습니다)',
    variants: { general: { label: '일반', endpoint: '/generate/video', opts: ['videosize', 'length', 'fps', 'steps'], sdProgress: true,
                placeholder: '예: a cat walking on a beach, waves, sunset, smooth motion',
                hint: 'AnimateDiff(로컬 sd-webui 확장)로 짧은 클립을 생성합니다. VRAM 8GB(RX 7600) 기준 16프레임·20스텝이 384×384 약 2.5분, 512×512 약 9분 걸립니다.' } }
  },
  music: {
    icon: '🎵', label: '음악', hasNegative: false, resultKind: 'audio', file: null,
    promptLabel: '프롬프트 (영어일수록 결과가 좋습니다)',
    variants: { general: { label: '일반', endpoint: '/generate/music', opts: ['duration'],
                placeholder: '예: lo-fi hip hop beat with soft piano and rain sounds',
                hint: '로컬 ACE-Step 1.5(MIT 라이선스, 상업 사용 가능)로 가사 없는 연주곡을 생성합니다. 처음 실행할 때는 ACE-Step 서버 기동과 모델 로딩 때문에 몇 분 더 걸립니다.' } }
  },
  text: {
    icon: '📝', label: '텍스트', hasNegative: false, resultKind: 'text',
    file: { label: '참고 파일(선택, txt/md 등)', accept: '.txt,.md,.csv,.json,.log,text/*', mode: 'text', field: 'attachment' },
    promptLabel: '프롬프트 (한글로 써도 됩니다)',
    variants: {
      chat: { label: '일상 대화', endpoint: '/generate/document', opts: ['websearch'],
              placeholder: '예: 오늘 저녁 뭐 먹을지 추천해줘',
              hint: '로컬 Ollama(qwen2.5:7b)와 자유롭게 대화합니다. "웹 검색 사용"을 켜면 무료 검색(DuckDuckGo) 결과를 참고해서 답합니다(검색 사이트가 방화벽에 막혀 있으면 실패하고 알고 있는 지식으로만 답합니다).' },
      document: { label: '문서', endpoint: '/generate/document', opts: ['websearch'],
                  placeholder: '예: 신제품 출시 안내 이메일을 정중한 어투로 작성해줘',
                  hint: '로컬 Ollama(qwen2.5:7b)로 생성합니다. 텍스트 파일을 첨부하면 그 내용을 참고해서 답합니다.' },
      instagram: { label: '인스타그램', endpoint: '/generate/social', extra: { platform: 'instagram' }, opts: [],
                   placeholder: '예: 원두 로스팅 카페 신메뉴 콜드브루 홍보',
                   hint: '인스타그램용 캡션+해시태그 초안만 생성합니다. 실제 게시는 하지 않습니다.' },
      tiktok: { label: '틱톡/릴스', endpoint: '/generate/social', extra: { platform: 'tiktok' }, opts: [],
                placeholder: '예: 원두 로스팅 카페 신메뉴 콜드브루 홍보',
                hint: '틱톡/릴스용 짧은 스크립트 초안만 생성합니다. 실제 게시는 하지 않습니다.' },
      blog: { label: '블로그', endpoint: '/generate/social', extra: { platform: 'blog' }, opts: [],
              placeholder: '예: 콜드브루 원두 추천 블로그 포스트',
              hint: '블로그 포스트 초안만 생성합니다.' },
      cafe: { label: '카페', endpoint: '/generate/social', extra: { platform: 'cafe' }, opts: [],
              placeholder: '예: 신메뉴 출시 안내 게시글',
              hint: '온라인 카페(네이버 카페 등) 게시글 초안만 생성합니다.' }
    }
  },
  code: {
    icon: '💻', label: '코드/앱', hasNegative: false,
    file: { label: '참고 파일(선택)', accept: '.txt,.md,.json,.log,.py,.js,.ts,.php,.java,.c,.cpp,.cs,.go,.rs,.html,text/*', mode: 'text', field: 'attachment' },
    promptLabel: '프롬프트 (한글로 써도 됩니다)',
    variants: {
      code: { label: '코드 텍스트', endpoint: '/generate/code', resultKind: 'code', opts: ['codelang'],
              placeholder: '예: 할 일을 추가·삭제할 수 있는 간단한 투두리스트 웹앱을 HTML 한 파일로 만들어줘',
              hint: '로컬 Ollama(qwen2.5-coder:7b)로 코드 텍스트 한 덩어리를 생성합니다(파일 자동 저장·실행·반복 수정은 안 함).' },
      ui: { label: 'UI 미리보기', endpoint: '/generate/ui', resultKind: 'ui', opts: [],
            placeholder: '예: 할 일 목록 앱의 메인 화면, 카드형 리스트에 완료 체크박스',
            hint: '동작하는 단일 HTML 화면을 생성해 바로 미리보기합니다. 디자이너 없이 초안을 빠르게 뽑는 용도입니다.' }
    }
  },
  voice: {
    icon: '🔊', label: '음성', hasNegative: false, resultKind: 'audio', isVoice: true,
    file: null,
    promptLabel: '텍스트 (읽어줄 문장)',
    variants: { general: { label: '일반', endpoint: '/generate/voice', opts: ['language', 'voice'],
                placeholder: '예: 안녕하세요, 오늘 회의는 3시에 시작합니다.',
                hint: '로컬 Supertonic 3(OpenRAIL-M, 상업 사용 가능)로 생성합니다. 정해진 목소리 10종 중에서 고릅니다(목소리 흉내는 지원하지 않음).' } }
  },
  model3d: {
    icon: '🧊', label: '3D 에셋', hasNegative: false, resultKind: 'model3d',
    file: { label: '참고 이미지(선택)', accept: 'image/*', mode: 'base64', field: 'image' },
    promptLabel: '프롬프트 (이미지를 첨부하면 비워도 됨)',
    variants: { general: { label: '일반', endpoint: '/generate/3d', opts: ['steps', 'guidance'],
                placeholder: '예: a low-poly wooden treasure chest, game asset',
                hint: '로컬 Shap-E로 생성합니다(.glb, Godot·Unity·웹 어디든 임포트 가능). 이미지 첨부 시 image-to-3D, 없으면 text-to-3D.' } }
  }
};

var currentType = 'image';
var typePicker = document.getElementById('type-picker');

Object.keys(GEN_TYPES).forEach(function (key) {
  var t = GEN_TYPES[key];
  var btn = document.createElement('div');
  btn.className = 'type-btn' + (key === currentType ? ' active' : '');
  btn.dataset.type = key;
  btn.innerHTML = '<span class="ico">' + t.icon + '</span>' + t.label;
  btn.addEventListener('click', function () { selectType(key); activatePanel('gen'); });
  typePicker.appendChild(btn);
});

function activatePanel(panelName) {
  document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
  var panel = document.getElementById('panel-' + panelName);
  if (panel) { panel.classList.add('active'); }

  document.querySelectorAll('.nav-item.tab').forEach(function (t) {
    t.classList.toggle('active', t.dataset.tab === panelName);
  });
  document.querySelectorAll('.type-btn').forEach(function (b) {
    b.classList.toggle('active', panelName === 'gen' && b.dataset.type === currentType);
  });
}

var currentVariant = null;
var variantPicker = document.getElementById('variant-picker');

function selectType(key) {
  currentType = key;
  var t = GEN_TYPES[key];
  var variantKeys = Object.keys(t.variants);
  currentVariant = variantKeys[0];

  document.querySelectorAll('.type-btn').forEach(function (b) {
    b.classList.toggle('active', b.dataset.type === key);
  });

  variantPicker.innerHTML = '';
  if (variantKeys.length > 1) {
    variantPicker.style.display = '';
    variantKeys.forEach(function (vk) {
      var vbtn = document.createElement('div');
      vbtn.className = 'variant-btn' + (vk === currentVariant ? ' active' : '');
      vbtn.dataset.variant = vk;
      vbtn.textContent = t.variants[vk].label;
      vbtn.addEventListener('click', function () { selectVariant(vk); });
      variantPicker.appendChild(vbtn);
    });
  } else {
    variantPicker.style.display = 'none';
  }

  document.getElementById('prompt-label').textContent = t.promptLabel;
  document.getElementById('gen-prompt').value = '';
  document.getElementById('gen-negative').value = '';
  document.getElementById('negative-label').style.display = t.hasNegative ? '' : 'none';
  document.getElementById('gen-negative').style.display = t.hasNegative ? '' : 'none';

  var fileLabel = document.getElementById('file-label');
  var fileInput = document.getElementById('gen-file');
  fileInput.value = '';
  if (t.file) {
    fileLabel.style.display = '';
    fileLabel.firstChild.textContent = t.file.label;
    fileInput.accept = t.file.accept;
  } else {
    fileLabel.style.display = 'none';
  }

  applyVariant();
}

function selectVariant(vk) {
  currentVariant = vk;
  document.querySelectorAll('.variant-btn').forEach(function (b) {
    b.classList.toggle('active', b.dataset.variant === vk);
  });
  applyVariant();
}

function applyVariant() {
  var t = GEN_TYPES[currentType];
  var v = t.variants[currentVariant];

  document.getElementById('gen-title').textContent = t.label + (v.label !== '일반' ? ' · ' + v.label : '') + ' 생성';
  document.getElementById('gen-prompt').placeholder = v.placeholder;

  document.querySelectorAll('[data-opt]').forEach(function (el) {
    el.style.display = v.opts.indexOf(el.dataset.opt) !== -1 ? '' : 'none';
  });

  document.getElementById('gen-submit').textContent = t.label + ' 생성';
  document.getElementById('gen-hint').textContent = v.hint;
  document.getElementById('gen-status').hidden = true;
  document.getElementById('gen-progress').style.display = 'none';
  document.getElementById('gen-result').innerHTML = '';
}

selectType(currentType);

document.querySelectorAll('.nav-item.tab').forEach(function (tab) {
  tab.addEventListener('click', function () { activatePanel(tab.dataset.tab); });
});

function readFileAsBase64(file, callback) {
  if (!file) { callback(null); return; }
  var reader = new FileReader();
  reader.onload = function () { callback(reader.result.split(',')[1]); };
  reader.readAsDataURL(file);
}

function readFileAsText(file, callback) {
  if (!file) { callback(''); return; }
  var reader = new FileReader();
  reader.onload = function () { callback(reader.result); };
  reader.readAsText(file);
}

// 결과 파일 다운로드 링크와, 게임 등에 넣을 때 어떤 모델·라이선스로 만들었는지 남겨 두는
// 출처 기록(<파일>.license.json) 링크를 붙인다. 파일 이름은 둘이 짝이 되도록 같은 이름을 쓴다.
function appendDownloads(resultEl, href, ext, provenance) {
  var name = currentType + '-' + new Date().toISOString().replace(/[-:]/g, '').slice(0, 15) + '.' + ext;
  var fileLink = document.createElement('a');
  fileLink.className = 'model-download';
  fileLink.href = href;
  fileLink.download = name;
  fileLink.textContent = '⬇ .' + ext + ' 파일 다운로드';
  resultEl.appendChild(fileLink);

  if (provenance) {
    var licLink = document.createElement('a');
    licLink.className = 'model-download';
    licLink.style.marginLeft = '14px';
    licLink.href = URL.createObjectURL(new Blob([JSON.stringify(provenance, null, 2)], { type: 'application/json' }));
    licLink.download = name + '.license.json';
    licLink.textContent = '⬇ 출처 기록(.license.json)';
    resultEl.appendChild(licLink);
  }
}

function renderResult(resultEl, kind, data) {
  if (kind === 'image' && data.images && data.images.length) {
    var img = document.createElement('img');
    img.src = 'data:image/png;base64,' + data.images[0];
    resultEl.appendChild(img);
    appendDownloads(resultEl, img.src, 'png', data.provenance);
    return true;
  }
  if (kind === 'video' && data.video) {
    var video = document.createElement('video');
    video.src = 'data:video/mp4;base64,' + data.video;
    video.controls = true; video.autoplay = true; video.loop = true;
    resultEl.appendChild(video);
    appendDownloads(resultEl, video.src, 'mp4', data.provenance);
    return true;
  }
  if (kind === 'audio' && data.audio) {
    var audio = document.createElement('audio');
    audio.src = 'data:audio/wav;base64,' + data.audio;
    audio.controls = true; audio.autoplay = true;
    resultEl.appendChild(audio);
    appendDownloads(resultEl, audio.src, 'wav', data.provenance);
    return true;
  }
  if (kind === 'text' && data.text) {
    var div = document.createElement('div');
    div.className = 'doc-text';
    div.textContent = data.text;
    resultEl.appendChild(div);
    return true;
  }
  if (kind === 'code' && data.text) {
    var pre = document.createElement('pre');
    pre.className = 'code-text';
    pre.textContent = data.text;
    resultEl.appendChild(pre);
    return true;
  }
  if (kind === 'ui' && data.text) {
    var fenceMatch = data.text.match(/```(?:html)?\s*([\s\S]*?)```/i);
    var html = fenceMatch ? fenceMatch[1] : data.text;

    var frame = document.createElement('iframe');
    frame.setAttribute('sandbox', 'allow-scripts');
    frame.style.width = '100%';
    frame.style.height = '480px';
    frame.style.border = '1px solid var(--border)';
    frame.style.borderRadius = '10px';
    frame.style.background = '#fff';
    frame.srcdoc = html;
    resultEl.appendChild(frame);

    var link = document.createElement('a');
    link.className = 'model-download';
    link.href = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
    link.download = 'ui.html';
    link.textContent = '⬇ .html 파일 다운로드';
    resultEl.appendChild(link);

    var pre2 = document.createElement('pre');
    pre2.className = 'code-text';
    pre2.style.marginTop = '12px';
    pre2.textContent = data.text;
    resultEl.appendChild(pre2);
    return true;
  }
  if (kind === 'model3d' && data.model) {
    var byteChars = atob(data.model);
    var bytes = new Uint8Array(byteChars.length);
    for (var i = 0; i < byteChars.length; i++) { bytes[i] = byteChars.charCodeAt(i); }
    var blobUrl = URL.createObjectURL(new Blob([bytes], { type: 'model/gltf-binary' }));

    var viewer = document.createElement('model-viewer');
    viewer.setAttribute('src', blobUrl);
    viewer.setAttribute('camera-controls', '');
    viewer.setAttribute('auto-rotate', '');
    viewer.setAttribute('shadow-intensity', '1');
    resultEl.appendChild(viewer);
    appendDownloads(resultEl, blobUrl, 'glb', data.provenance);
    return true;
  }
  return false;
}

// sd-webui 진행률(/progress/sd)을 폴링해 실제 %를 보여준다.
function pollSdProgress(statusEl, progressBar) {
  var stopped = false;
  function tick() {
    if (stopped) { return; }
    fetch('/progress/sd')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (stopped) { return; }
        if (data.ok) {
          var pct = Math.max(0, Math.min(100, Math.round((data.progress || 0) * 100)));
          progressBar.style.width = pct + '%';
          var eta = data.eta_relative > 0 ? ' · 남은 약 ' + Math.ceil(data.eta_relative) + '초' : '';
          statusEl.textContent = '생성 중입니다… (' + pct + '%' + eta + ')';
        }
        if (!stopped) { setTimeout(tick, 800); }
      })
      .catch(function () { if (!stopped) { setTimeout(tick, 1500); } });
  }
  tick();
  return function stop() { stopped = true; };
}

// sd-webui 외 백엔드(음악·음성·3D·Ollama 계열)는 진행률 API가 없어서, 예상 소요시간 기준
// 경과시간 비례(점근선 92%까지)로 "예상 %"를 보여준다. 실제 응답이 오면 100%로 마무리된다.
function simulateProgress(statusEl, progressBar, estSeconds) {
  var start = Date.now();
  var stopped = false;
  var timer = setInterval(function () {
    if (stopped) { return; }
    var elapsed = (Date.now() - start) / 1000;
    var pct = Math.min(92, Math.round(92 * (1 - Math.exp(-elapsed / estSeconds))));
    progressBar.style.width = pct + '%';
    statusEl.textContent = '생성 중입니다… (예상 ' + pct + '%)';
  }, 400);
  return function stop() { stopped = true; clearInterval(timer); };
}

// 진행률 API가 없는 백엔드용 대략적인 예상 소요시간(초).
function estimateSeconds(type, variant, body) {
  if (type === 'music') { return Math.max(20, (body.duration || 30) * 2); }
  if (type === 'voice') { return Math.max(3, ((body.text || '').length / 30)); }
  if (type === 'model3d') { return Math.max(15, ((body.steps || 64) / 64) * 45); }
  if (type === 'code' && variant === 'ui') { return 30; }
  if (type === 'code') { return 20; }
  if (body.web_search) { return 20; }
  return 14;
}

document.getElementById('gen-submit').addEventListener('click', function () {
  var btn = this;
  var t = GEN_TYPES[currentType];
  var v = t.variants[currentVariant];
  var statusEl = document.getElementById('gen-status');
  var resultEl = document.getElementById('gen-result');
  var progressEl = document.getElementById('gen-progress');
  var progressBar = document.getElementById('gen-progress-bar');
  var prompt = document.getElementById('gen-prompt').value.trim();
  var negative = document.getElementById('gen-negative').value.trim();
  var file = t.file ? document.getElementById('gen-file').files[0] : null;

  var requirePrompt = !(currentType === 'model3d' && file);
  if (requirePrompt && !prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = t.isVoice ? '텍스트를 입력해 주세요.' : '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 준비 중…';
  progressEl.style.display = '';
  progressBar.style.width = '0%';
  resultEl.innerHTML = '';

  var readFn = t.file && t.file.mode === 'text' ? readFileAsText : readFileAsBase64;

  readFn(file, function (fileValue) {
    var body = {};

    if (t.isVoice) {
      body.text = prompt;
    } else {
      body.prompt = prompt;
    }
    if (t.hasNegative) { body.negative_prompt = negative; }
    if (t.file && fileValue) { body[t.file.field] = fileValue; }
    if (v.extra) { Object.keys(v.extra).forEach(function (k) { body[k] = v.extra[k]; }); }

    if (v.opts.indexOf('size') !== -1) {
      var size = document.getElementById('opt-size').value.split('x');
      body.width = parseInt(size[0], 10);
      body.height = parseInt(size[1], 10);
    }
    if (v.opts.indexOf('videosize') !== -1) {
      body.width = body.height = parseInt(document.getElementById('opt-videosize').value, 10);
    }
    if (v.opts.indexOf('steps') !== -1) { body.steps = parseInt(document.getElementById('opt-steps').value, 10); }
    if (v.opts.indexOf('length') !== -1) { body.video_length = parseInt(document.getElementById('opt-length').value, 10); }
    if (v.opts.indexOf('fps') !== -1) { body.fps = parseInt(document.getElementById('opt-fps').value, 10); }
    if (v.opts.indexOf('duration') !== -1) { body.duration = parseInt(document.getElementById('opt-duration').value, 10); }
    if (v.opts.indexOf('guidance') !== -1) { body.guidance_scale = parseFloat(document.getElementById('opt-guidance').value); }
    if (v.opts.indexOf('codelang') !== -1) { body.language = document.getElementById('opt-codelang').value; }
    if (v.opts.indexOf('language') !== -1) { body.language = document.getElementById('opt-language').value; }
    if (v.opts.indexOf('voice') !== -1) { body.voice = document.getElementById('opt-voice').value; }
    if (v.opts.indexOf('websearch') !== -1) { body.web_search = document.getElementById('opt-websearch').checked; }

    statusEl.textContent = '생성 중입니다…';
    var stopProgress = v.sdProgress
      ? pollSdProgress(statusEl, progressBar)
      : simulateProgress(statusEl, progressBar, estimateSeconds(currentType, currentVariant, body));

    fetch(v.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        stopProgress();
        btn.disabled = false;
        if (data.ok && renderResult(resultEl, v.resultKind || t.resultKind, data)) {
          progressBar.style.width = '100%';
          setTimeout(function () { progressEl.style.display = 'none'; }, 300);
          statusEl.hidden = true;
        } else {
          progressEl.style.display = 'none';
          statusEl.className = 'gen-status error';
          statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
        }
      })
      .catch(function (err) {
        stopProgress();
        progressEl.style.display = 'none';
        btn.disabled = false;
        statusEl.className = 'gen-status error';
        statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
      });
  });
});

function refreshStatus() {
  var listEl = document.getElementById('status-list');
  listEl.innerHTML = '확인 중…';
  fetch('/status')
    .then(function (res) { return res.json(); })
    .then(function (data) {
      listEl.innerHTML = '';
      (data.services || []).forEach(function (svc) {
        var row = document.createElement('div');
        row.className = 'status-row';
        var dot = document.createElement('span');
        dot.className = 'status-dot ' + (svc.ok ? 'up' : 'down');
        var label = document.createElement('span');
        label.className = 'status-label';
        label.textContent = svc.name;
        var text = document.createElement('span');
        text.className = 'status-text ' + (svc.ok ? 'up' : 'down');
        text.textContent = svc.ok ? '켜짐' : '꺼짐';
        row.appendChild(dot);
        row.appendChild(label);
        row.appendChild(text);
        listEl.appendChild(row);
      });
    })
    .catch(function (err) {
      listEl.textContent = '상태 확인 실패: ' + err;
    });
}
document.getElementById('status-refresh').addEventListener('click', refreshStatus);
refreshStatus();
</script>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}
