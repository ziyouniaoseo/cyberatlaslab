/**
 * CyberAtlasLab — Category/Best X 页面原型脚本
 * 文件：prototype/category-best-x.js
 *
 * WordPress 整合说明：
 * - 将本文件拆分到子主题 js/ 目录，通过 wp_enqueue_script() 入队
 * - CAL_CONTENT / CAL_PRODUCTS 可替换为 PHP wp_localize_script() 注入的数据
 * - 所有 DOM 选择器保持稳定，迁移时不需要修改 JS 逻辑
 */

'use strict';

/* ==========================================================================
   数据层 — 三个文案版本
   WordPress 整合时通过 wp_localize_script 注入 PHP/ACF 数据替换
   ========================================================================== */
const CAL_CONTENT = {
  /**
   * version-a: 标准版（默认展示）
   * 适用于大多数浏览场景，平衡信息密度与可读性
   */
  'version-a': {
    'breadcrumb-current': 'VPN',
    'hero-badge': 'VPN 评测',
    'hero-h1': 'Best VPN Services 2025',
    'hero-intro': 'Our security experts have tested over 30 VPN services using our 47-point evaluation framework. Here are the top picks for speed, privacy, and value.',
    'hero-trust': [
      { icon: 'check', label: '30+ VPNs Tested' },
      { icon: 'clock', label: 'Updated August 2025' },
      { icon: 'shield', label: 'Independent Testing' },
      { icon: 'star', label: 'Expert Reviews' },
    ],
    'hero-filters': ['All', 'Speed', 'Privacy', 'Streaming', 'Budget', 'Business'],
    'howwetest-title': 'How We Test & Rank VPN Services',
    'howwetest-intro': 'Independent methodology for unbiased results.',
    'howwetest-items': [
      { icon: 'speed', title: 'Speed Tests', desc: 'We run 500+ speed tests across 20+ global server locations using standardized hardware.' },
      { icon: 'shield', title: 'Security Audit', desc: 'DNS leak tests, kill-switch verification, and protocol security analysis on all platforms.' },
      { icon: 'eye', title: 'Privacy Review', desc: 'We examine privacy policies, no-logs claims, and third-party audit reports.' },
      { icon: 'device', title: 'Usability Testing', desc: 'Real-world app testing on Windows, macOS, iOS, Android, and Linux.' },
      { icon: 'globe', title: 'Server Coverage', desc: 'We verify server counts, geographic distribution, and specialty server availability.' },
      { icon: 'dollar', title: 'Value Assessment', desc: 'Pricing analysis across all plans with feature comparison and refund policy review.' },
    ],
    'comparison-title': 'Quick Comparison',
    'comparison-subtitle': 'Top 5 VPN Services at a Glance',
    'picks-title': "Editor's Picks",
    'picks-subtitle': 'Our top-rated VPN services for 2025',
    'guide-title': 'Buying Guide',
    'guide-subtitle': 'How to Choose the Right VPN',
    'guide-intro': 'Not all VPNs are created equal. Here\'s what to look for when choosing a VPN service that fits your needs and budget.',
    'faq-title': 'Frequently Asked Questions',
    'related-title': 'Related Categories',
  },

  /**
   * version-b: 移动端短文案版
   * 精简标题和描述，适合小屏幕快速浏览
   */
  'version-b': {
    'breadcrumb-current': 'VPN',
    'hero-badge': 'VPN',
    'hero-h1': 'Best VPNs 2025',
    'hero-intro': 'Expert-tested VPN rankings for speed, privacy, and value.',
    'hero-trust': [
      { icon: 'check', label: '30+ Tested' },
      { icon: 'clock', label: 'Aug 2025' },
    ],
    'hero-filters': ['All', 'Speed', 'Privacy', 'Budget'],
    'howwetest-title': 'How We Test VPNs',
    'howwetest-intro': 'Our 47-point testing framework.',
    'howwetest-items': [
      { icon: 'speed', title: 'Speed Tests', desc: '500+ speed tests across 20+ global locations.' },
      { icon: 'shield', title: 'Security', desc: 'DNS leak tests and kill-switch verification.' },
      { icon: 'eye', title: 'Privacy', desc: 'Policy review and audit reports.' },
    ],
    'comparison-title': 'Compare Top VPNs',
    'comparison-subtitle': 'Swipe to compare',
    'picks-title': 'Top VPNs',
    'picks-subtitle': 'Expert picks for 2025',
    'guide-title': 'How to Choose',
    'guide-subtitle': 'Key things to look for',
    'guide-intro': 'Here\'s what matters when picking a VPN.',
    'faq-title': 'FAQs',
    'related-title': 'More Categories',
  },

  /**
   * version-c: 桌面端长文案版
   * 更丰富的描述文字，适合深度阅读的桌面端用户
   */
  'version-c': {
    'breadcrumb-current': 'VPN Services',
    'hero-badge': 'VPN 深度测评 2025',
    'hero-h1': 'Best VPN Services of 2025: Expert-Tested Rankings',
    'hero-intro': 'Our cybersecurity research team has rigorously tested and evaluated over 30 leading VPN services using our comprehensive 47-point methodology. We assess encryption strength, real-world connection speeds across 20+ global server locations, privacy policy compliance, application usability, and overall value for money — so you can make an informed decision.',
    'hero-trust': [
      { icon: 'check', label: '30+ VPN Services Tested' },
      { icon: 'clock', label: 'Last Updated: August 2025' },
      { icon: 'shield', label: 'Independent, Unbiased Testing' },
      { icon: 'star', label: 'By Certified Security Experts' },
    ],
    'hero-filters': ['All VPNs', 'Fastest Speed', 'Best Privacy', 'Streaming', 'Best Budget', 'For Business', 'Multi-Device'],
    'howwetest-title': 'How We Test & Rank VPN Services: Our Independent Methodology',
    'howwetest-intro': 'Our 47-point evaluation framework ensures every recommendation is backed by rigorous, reproducible testing.',
    'howwetest-items': [
      { icon: 'speed', title: 'Speed Performance Testing', desc: 'We conduct 500+ connection speed tests across 20+ global server locations using standardized Gigabit hardware, measuring download, upload, and latency impact.' },
      { icon: 'shield', title: 'Security & Encryption Audit', desc: 'DNS and WebRTC leak tests, kill-switch verification, protocol security analysis, and cipher suite evaluation on all supported platforms.' },
      { icon: 'eye', title: 'Privacy Policy Analysis', desc: 'We scrutinize no-logs claims, jurisdiction, third-party audit reports, and warrant canary policies to assess real-world privacy protection.' },
      { icon: 'device', title: 'Multi-Platform Usability', desc: 'Real-world app testing conducted over 30+ days on Windows, macOS, iOS, Android, Linux, and router implementations.' },
      { icon: 'globe', title: 'Server Network Evaluation', desc: 'We independently verify server counts, geographic distribution, P2P support, obfuscated server availability, and colocation practices.' },
      { icon: 'dollar', title: 'Pricing & Value Analysis', desc: 'Comprehensive pricing analysis across all subscription tiers, simultaneous connection limits, money-back guarantees, and long-term cost projections.' },
    ],
    'comparison-title': 'Side-by-Side VPN Comparison',
    'comparison-subtitle': 'Compare the top 5 VPN services on the metrics that matter most',
    'picks-title': "Editor's Picks: Best VPN Services for 2025",
    'picks-subtitle': 'Our top-rated VPN services, selected for performance, privacy, and overall value',
    'guide-title': 'Complete VPN Buying Guide',
    'guide-subtitle': 'Everything You Need to Know Before Buying a VPN',
    'guide-intro': 'Choosing the right VPN involves more than just picking the most popular option. Different use cases demand different features, and understanding what each specification actually means in practice will help you make a better decision.',
    'faq-title': 'Frequently Asked Questions About VPNs',
    'related-title': 'Explore Related Security Categories',
  },
};

/* ==========================================================================
   产品数据（WordPress 整合时通过 wp_localize_script + ACF/WP_Query 替换）
   ========================================================================== */
const CAL_PRODUCTS = [
  {
    rank: 1,
    slug: 'nordvpn',
    name: 'NordVPN',
    initials: 'N',
    color: '#4E5DDE',
    score: 9.8,
    price: '$3.99/mo',
    priceFull: '$3.99/mo (2-yr plan)',
    bestFor: 'Best Overall VPN',
    tagline: 'The gold standard for all-around VPN performance',
    desc: 'NordVPN consistently tops our charts across every category we test. With over 6,200 servers in 111 countries, NordLynx protocol delivering the fastest speeds, and a twice-audited no-logs policy, it delivers premium security without compromising on usability or performance.',
    features: [
      '6,200+ servers in 111 countries',
      'NordLynx & OpenVPN protocols',
      'Twice-audited no-logs policy',
      'Threat Protection malware blocker',
      'Up to 10 simultaneous devices',
      '30-day money-back guarantee',
    ],
    scores: [
      { name: 'Speed', val: 9.9 },
      { name: 'Security', val: 9.8 },
      { name: 'Ease of Use', val: 9.6 },
      { name: 'Value', val: 9.7 },
    ],
    ctaUrl: '#',
    reviewUrl: '#',
    isTopPick: true,
  },
  {
    rank: 2,
    slug: 'expressvpn',
    name: 'ExpressVPN',
    initials: 'E',
    color: '#DA3940',
    score: 9.6,
    price: '$6.67/mo',
    priceFull: '$6.67/mo (15-mo plan)',
    bestFor: 'Speed & Streaming',
    tagline: 'Unbeatable streaming performance and reliability',
    desc: 'ExpressVPN excels at unblocking streaming services and delivering consistently fast speeds, thanks to its proprietary Lightway protocol. Its TrustedServer technology ensures your data is never written to disk.',
    features: [
      '3,000+ servers in 105 countries',
      'Lightway protocol',
      'TrustedServer (RAM-only)',
      'Works with Netflix, Disney+, BBC iPlayer',
      '8 simultaneous connections',
      '30-day money-back guarantee',
    ],
    scores: [
      { name: 'Speed', val: 9.9 },
      { name: 'Security', val: 9.5 },
      { name: 'Streaming', val: 9.9 },
      { name: 'Value', val: 9.1 },
    ],
    ctaUrl: '#',
    reviewUrl: '#',
    isTopPick: false,
  },
  {
    rank: 3,
    slug: 'surfshark',
    name: 'Surfshark',
    initials: 'S',
    color: '#1E98D5',
    score: 9.4,
    price: '$2.19/mo',
    priceFull: '$2.19/mo (2-yr plan)',
    bestFor: 'Budget Value',
    tagline: 'Best-value VPN with unlimited device support',
    desc: 'Surfshark punches well above its price point, offering unlimited simultaneous connections — a standout feature. Its clean apps and solid security credentials make it our top budget pick.',
    features: [
      '3,200+ servers in 100 countries',
      'Unlimited simultaneous devices',
      'CleanWeb ad/tracker blocker',
      'MultiHop double VPN',
      'Camouflage mode',
      '30-day money-back guarantee',
    ],
    scores: [
      { name: 'Speed', val: 9.3 },
      { name: 'Security', val: 9.4 },
      { name: 'Value', val: 9.9 },
      { name: 'Features', val: 9.2 },
    ],
    ctaUrl: '#',
    reviewUrl: '#',
    isTopPick: false,
  },
  {
    rank: 4,
    slug: 'cyberghost',
    name: 'CyberGhost',
    initials: 'CG',
    color: '#FFCC00',
    score: 9.1,
    price: '$2.03/mo',
    priceFull: '$2.03/mo (2-yr plan)',
    bestFor: 'Beginners',
    tagline: 'Easiest VPN to set up and use',
    desc: 'CyberGhost is ideal for VPN newcomers, with dedicated streaming and torrenting profiles that take the guesswork out of configuration. Its massive server network covers 100 countries.',
    features: [
      '9,700+ servers in 100 countries',
      'Dedicated streaming servers',
      'One-click connect profiles',
      'NoSpy servers option',
      '7 simultaneous connections',
      '45-day money-back guarantee',
    ],
    scores: [
      { name: 'Ease of Use', val: 9.7 },
      { name: 'Speed', val: 8.9 },
      { name: 'Value', val: 9.4 },
      { name: 'Security', val: 8.9 },
    ],
    ctaUrl: '#',
    reviewUrl: '#',
    isTopPick: false,
  },
  {
    rank: 5,
    slug: 'pia',
    name: 'Private Internet Access',
    initials: 'PIA',
    color: '#5BA829',
    score: 8.9,
    price: '$2.03/mo',
    priceFull: '$2.03/mo (3-yr plan)',
    bestFor: 'Power Users',
    tagline: 'Highly configurable open-source VPN',
    desc: 'PIA gives technically-minded users granular control over their VPN configuration, with open-source apps that have been independently audited. It supports a wide range of protocols and advanced features.',
    features: [
      '35,000+ servers in 91 countries',
      'Open-source, audited apps',
      'MACE ad/malware blocker',
      'WireGuard & OpenVPN support',
      '10 simultaneous connections',
      '30-day money-back guarantee',
    ],
    scores: [
      { name: 'Customization', val: 9.8 },
      { name: 'Speed', val: 8.7 },
      { name: 'Value', val: 9.5 },
      { name: 'Security', val: 9.0 },
    ],
    ctaUrl: '#',
    reviewUrl: '#',
    isTopPick: false,
  },
];

/* FAQ 数据 */
const CAL_FAQS = [
  {
    q: 'Is it safe to use a free VPN?',
    a: 'Free VPNs come with significant risks. Many free providers monetize your data by selling browsing information to third parties, defeating the purpose of using a VPN for privacy. Some free VPNs have been found to contain malware. For reliable privacy and security, a paid VPN with a verified no-logs policy is strongly recommended. If budget is a concern, many premium VPNs offer affordable long-term plans starting under $3/month.',
  },
  {
    q: 'Will a VPN slow down my internet connection?',
    a: 'Yes, all VPNs add some overhead due to encryption, but the best VPNs minimize this impact significantly. Modern protocols like WireGuard and NordLynx can deliver 90%+ of your base connection speed. In our testing, NordVPN and ExpressVPN showed less than 10% speed reduction on average. Speed impact is most noticeable on slower base connections.',
  },
  {
    q: 'Can a VPN unblock Netflix and other streaming services?',
    a: 'Many premium VPNs can unblock Netflix, Disney+, BBC iPlayer, and other streaming platforms, but results vary. Streaming services actively block VPN IP addresses, so this is an ongoing cat-and-mouse game. ExpressVPN and NordVPN have the best track records for consistent streaming access. For the most current unblocking status, always check the provider\'s website.',
  },
  {
    q: 'What is a no-logs VPN policy?',
    a: 'A no-logs (or zero-logs) policy means the VPN provider does not collect or store any records of your online activity, connection timestamps, IP addresses, or DNS queries. This is crucial for privacy — if a provider has no data, it cannot be handed over to authorities or hackers. Look for providers whose no-logs claims have been independently audited by reputable firms like Deloitte or PwC.',
  },
  {
    q: 'How many devices can I protect with one VPN subscription?',
    a: 'This varies by provider. Most premium VPNs allow between 6–10 simultaneous connections per account. Surfshark is exceptional, offering unlimited simultaneous connections. If you need to protect your entire home network, look for VPNs that support router installation, which effectively covers all devices connected to your Wi-Fi.',
  },
];

/* 相关分类数据 */
const CAL_RELATED = [
  { name: 'Password Managers', count: '12 products', icon: 'lock', href: '#' },
  { name: 'Antivirus Software', count: '18 products', icon: 'shield', href: '#' },
  { name: 'Firewalls', count: '8 products', icon: 'wall', href: '#' },
  { name: 'Identity Protection', count: '10 products', icon: 'user', href: '#' },
];

/* How We Test 因素（从 CAL_CONTENT 的 howwetest-items 渲染，这里提供图标映射） */
const CAL_ICON_MAP = {
  speed: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2l1.5 4H14l-3.5 2.5 1.5 4.5L8 10.5 4.5 13l1.5-4.5L2 6h4.5L8 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
  shield: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1.5L2.5 4v5c0 4 2.5 6 5.5 6.5 3-0.5 5.5-2.5 5.5-6.5V4L8 1.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M5.5 8l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  eye: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>`,
  device: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M5 14h6M8 11v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
  globe: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M2 8h12M8 2a10 10 0 000 12M8 2a10 10 0 010 12" stroke="currentColor" stroke-width="1.5"/></svg>`,
  dollar: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M5.5 5.5a2.5 2.5 0 015 0c0 1.5-5 2-5 4a2.5 2.5 0 005 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  check: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 8l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  clock: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3.5l2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
  star: `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1l1.5 4.5H14l-3.75 2.7 1.5 4.55L8 10.2 4.25 12.75l1.5-4.55L2 5.5h4.5L8 1z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
  lock: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="4" y="9" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 9V7a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
  wall: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="4" width="16" height="4" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="10" width="16" height="4" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="16" width="6" height="3" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="12" y="16" width="6" height="3" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>`,
  user: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M3 18c0-3.3 3.1-6 7-6s7 2.7 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
};

/* ==========================================================================
   当前激活版本状态
   ========================================================================== */
let calCurrentVersion = 'version-a';

/* ==========================================================================
   工具函数
   ========================================================================== */

/** 创建评分环 SVG 的 stroke-dasharray/dashoffset 参数 */
function calScoreDash(score, max = 10, r = 42) {
  const circumference = 2 * Math.PI * r;
  const pct = Math.max(0, Math.min(score / max, 1));
  return { circumference, dashoffset: circumference * (1 - pct) };
}

/** 安全的 HTML 转义（防止 XSS；WordPress 整合时 PHP 端已做转义，JS 端也保持习惯） */
function esc(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/* ==========================================================================
   渲染函数
   ========================================================================== */

/** 渲染信任信号列表 */
function calRenderTrust(items) {
  const el = document.querySelector('[data-cal-content="hero-trust"]');
  if (!el) return;
  const iconColors = { check: 'cal-trust-item__icon', clock: 'cal-trust-item__icon--yellow', shield: 'cal-trust-item__icon--blue', star: 'cal-trust-item__icon--yellow' };
  el.innerHTML = items.map(item => `
    <li class="cal-trust-item">
      <span class="cal-trust-item__icon ${iconColors[item.icon] || ''}" aria-hidden="true">${CAL_ICON_MAP[item.icon] || ''}</span>
      <span>${esc(item.label)}</span>
    </li>
  `).join('');
}

/** 渲染快速筛选 Pill */
function calRenderFilters(filters) {
  const el = document.querySelector('[data-cal-content="hero-filters"]');
  if (!el) return;
  el.innerHTML = filters.map((f, i) => `
    <button class="cal-filter-pill${i === 0 ? ' is-active' : ''}" type="button">${esc(f)}</button>
  `).join('');
  // 筛选 pill 点击交互
  el.querySelectorAll('.cal-filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
      el.querySelectorAll('.cal-filter-pill').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
    });
  });
}

/** 渲染 How We Test 因素网格 */
function calRenderHowWeTestItems(items) {
  const el = document.querySelector('[data-cal-content="howwetest-items"]');
  if (!el) return;
  el.innerHTML = items.map(item => `
    <div class="cal-test-factor">
      <div class="cal-test-factor__icon" aria-hidden="true">${CAL_ICON_MAP[item.icon] || ''}</div>
      <div>
        <h3 class="cal-test-factor__title">${esc(item.title)}</h3>
        <p class="cal-test-factor__desc">${esc(item.desc)}</p>
      </div>
    </div>
  `).join('');
}

/** 渲染比较表 tbody（桌面端） */
function calRenderComparisonTable(products) {
  const tbody = document.getElementById('cal-comparison-tbody');
  if (!tbody) return;
  tbody.innerHTML = products.map(p => `
    <tr${p.isTopPick ? ' class="is-top-pick"' : ''}>
      <td class="cal-ct-rank">
        <span class="cal-badge cal-badge--rank${p.rank === 1 ? ' cal-badge--rank-1' : ''}">#${p.rank}</span>
      </td>
      <td>
        <div class="cal-ct-product">
          <div class="cal-ct-logo" style="background:${esc(p.color)}20;color:${esc(p.color)}">${esc(p.initials)}</div>
          <span class="cal-ct-name">${esc(p.name)}</span>
        </div>
      </td>
      <td class="cal-ct-score">
        <span class="cal-ct-score-val">${esc(String(p.score))}</span>
        <span class="cal-ct-score-max">/10</span>
      </td>
      <td class="cal-ct-price">${esc(p.price)}</td>
      <td class="cal-ct-best-for">${esc(p.bestFor)}</td>
      <td class="cal-ct-cta">
        <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--primary cal-btn--sm" rel="noopener">Visit Site</a>
      </td>
    </tr>
  `).join('');
}

/** 渲染移动端横向滑动对比卡片 */
function calRenderComparisonCards(products) {
  const wrap = document.getElementById('cal-comparison-cards');
  if (!wrap) return;
  wrap.innerHTML = products.map(p => `
    <div class="cal-comparison-card${p.isTopPick ? ' is-top-pick' : ''}">
      <div class="cal-comparison-card__header">
        <span class="cal-comparison-card__rank">
          <span class="cal-badge cal-badge--rank${p.rank === 1 ? ' cal-badge--rank-1' : ''}">#${p.rank}</span>
        </span>
        <div class="cal-comparison-card__logo" style="background:${esc(p.color)}20;color:${esc(p.color)}">${esc(p.initials)}</div>
        <div class="cal-comparison-card__name">${esc(p.name)}</div>
      </div>
      <div class="cal-comparison-card__stats">
        <div class="cal-comparison-card__stat">
          <span class="cal-comparison-card__stat-label">Score</span>
          <span class="cal-comparison-card__stat-val">${esc(String(p.score))}/10</span>
        </div>
        <div class="cal-comparison-card__stat">
          <span class="cal-comparison-card__stat-label">Price</span>
          <span class="cal-comparison-card__stat-val">${esc(p.price)}</span>
        </div>
      </div>
      <div class="cal-comparison-card__best-for">
        <strong>Best For:</strong> ${esc(p.bestFor)}
      </div>
      <div class="cal-comparison-card__cta">
        <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--primary cal-btn--sm" style="width:100%;text-align:center;" rel="noopener">Visit Site</a>
      </div>
    </div>
  `).join('');
}

/** 渲染 #1 大卡片（Editor's Picks） */
function calRenderFeaturedCard(p) {
  const wrap = document.getElementById('cal-picks-featured');
  if (!wrap) return;
  const { circumference, dashoffset } = calScoreDash(p.score);
  const featuresHtml = p.features.slice(0, 6).map(f => `
    <div class="cal-pick-card__feature">
      <svg class="cal-pick-card__feature-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M4.5 7l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>${esc(f)}</span>
    </div>
  `).join('');
  const scoreBreakdownHtml = p.scores.map(s => `
    <div class="cal-score-breakdown__item">
      <span class="cal-score-breakdown__name">${esc(s.name)}</span>
      <div class="cal-score-breakdown__bar-wrap"><div class="cal-score-breakdown__bar" style="width:${(s.val / 10 * 100).toFixed(0)}%"></div></div>
      <span class="cal-score-breakdown__val">${esc(String(s.val))}</span>
    </div>
  `).join('');
  wrap.innerHTML = `
    <div class="cal-pick-card--featured">
      <span class="cal-pick-card__rank-badge">
        <span class="cal-badge cal-badge--rank cal-badge--rank-1">#1 Editor's Choice</span>
      </span>
      <div class="cal-pick-card__body">
        <div class="cal-pick-card__header">
          <div class="cal-pick-card__logo" style="background:${esc(p.color)}15;color:${esc(p.color)}">${esc(p.initials)}</div>
          <div class="cal-pick-card__title-group">
            <h3 class="cal-pick-card__name">${esc(p.name)}</h3>
            <span class="cal-pick-card__best-for">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1l1.5 4.5H12l-3.75 2.7 1.5 4.55L7 10.5 3.25 12.75l1.5-4.55L1 5.5h4.5L7 1z" fill="currentColor"/></svg>
              ${esc(p.bestFor)}
            </span>
          </div>
        </div>
        <p class="cal-pick-card__desc">${esc(p.desc)}</p>
        <div class="cal-pick-card__features">${featuresHtml}</div>
        <div class="cal-pick-card__meta">
          <div class="cal-pick-card__meta-item">
            <span class="cal-pick-card__meta-label">Overall Score</span>
            <span class="cal-pick-card__meta-val cal-pick-card__meta-val--score">${esc(String(p.score))}<small style="font-size:0.6em;color:#64748B">/10</small></span>
          </div>
          <div class="cal-pick-card__meta-item">
            <span class="cal-pick-card__meta-label">Starting Price</span>
            <span class="cal-pick-card__meta-val">${esc(p.priceFull)}</span>
          </div>
        </div>
        <div class="cal-pick-card__cta-group">
          <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--primary" rel="noopener">Visit ${esc(p.name)} →</a>
          <a href="${esc(p.reviewUrl)}" class="cal-btn cal-btn--ghost">Read Full Review</a>
        </div>
      </div>
      <!-- 评分面板（仅桌面端显示） -->
      <div class="cal-pick-card__score-panel" aria-label="${esc(p.name)} 评分详情">
        <div class="cal-score-ring" aria-hidden="true">
          <svg class="cal-score-ring__svg" width="100" height="100" viewBox="0 0 100 100">
            <circle class="cal-score-ring__track" cx="50" cy="50" r="42"/>
            <circle class="cal-score-ring__fill" cx="50" cy="50" r="42"
              stroke-dasharray="${circumference.toFixed(2)}"
              stroke-dashoffset="${dashoffset.toFixed(2)}"/>
          </svg>
          <div class="cal-score-ring__text">
            <span class="cal-score-ring__num">${esc(String(p.score))}</span>
            <span class="cal-score-ring__label">/ 10</span>
          </div>
        </div>
        <div class="cal-score-breakdown">${scoreBreakdownHtml}</div>
      </div>
    </div>
  `;
}

/** 渲染 #2–3 中型卡片 */
function calRenderMidCards(products) {
  const wrap = document.getElementById('cal-picks-mid');
  if (!wrap) return;
  wrap.innerHTML = products.map(p => {
    const featuresHtml = p.features.slice(0, 4).map(f => `
      <div class="cal-pick-card__feature">
        <svg class="cal-pick-card__feature-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M4.5 7l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>${esc(f)}</span>
      </div>
    `).join('');
    return `
      <div class="cal-pick-card--mid">
        <span class="cal-badge cal-badge--rank">#${p.rank}</span>
        <div class="cal-pick-card__header">
          <div class="cal-pick-card__logo" style="background:${esc(p.color)}15;color:${esc(p.color)}">${esc(p.initials)}</div>
          <div class="cal-pick-card__title-group">
            <h3 class="cal-pick-card__name">${esc(p.name)}</h3>
            <span class="cal-pick-card__best-for" style="font-size:0.8rem">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M6 1l1.2 3.5H11L8 6.8l1.2 3.7L6 8.5 2.8 10.5 4 6.8 1 4.5h3.8L6 1z" fill="currentColor"/></svg>
              ${esc(p.bestFor)}
            </span>
          </div>
        </div>
        <p style="font-size:0.875rem;color:#475569;line-height:1.625;margin:0 0 0.75rem">${esc(p.desc.slice(0, 160))}…</p>
        <div class="cal-pick-card__features">${featuresHtml}</div>
        <div class="cal-pick-card__meta" style="margin-bottom:0.75rem">
          <div class="cal-pick-card__meta-item">
            <span class="cal-pick-card__meta-label">Score</span>
            <span class="cal-pick-card__meta-val" style="color:#2563EB">${esc(String(p.score))}/10</span>
          </div>
          <div class="cal-pick-card__meta-item">
            <span class="cal-pick-card__meta-label">Price</span>
            <span class="cal-pick-card__meta-val">${esc(p.price)}</span>
          </div>
        </div>
        <div class="cal-pick-card__cta-group">
          <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--primary cal-btn--sm" rel="noopener">Visit Site</a>
          <a href="${esc(p.reviewUrl)}" class="cal-btn cal-btn--outline cal-btn--sm">Review</a>
        </div>
      </div>
    `;
  }).join('');
}

/** 渲染 #4–5 小卡片 */
function calRenderMinorCards(products) {
  const wrap = document.getElementById('cal-picks-minor-inner');
  if (!wrap) return;
  wrap.innerHTML = products.map(p => `
    <div class="cal-pick-card--minor">
      <span class="cal-badge cal-badge--rank">#${p.rank}</span>
      <div class="cal-pick-card__logo" style="width:40px;height:40px;background:${esc(p.color)}15;color:${esc(p.color)};border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.875rem;flex-shrink:0">${esc(p.initials)}</div>
      <div class="cal-pick-card--minor-info">
        <h3 class="cal-pick-card__name">${esc(p.name)}</h3>
        <div class="cal-pick-card--minor-meta">
          <span>${esc(String(p.score))}/10</span>
          <span>·</span>
          <span>${esc(p.price)}</span>
          <span>·</span>
          <span>${esc(p.bestFor)}</span>
        </div>
      </div>
      <div class="cal-pick-card--minor-cta">
        <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--outline cal-btn--sm" rel="noopener">Visit</a>
      </div>
    </div>
  `).join('');
}

/** 渲染侧边推荐产品卡 */
function calRenderSidebarProduct(p) {
  const wrap = document.getElementById('cal-guide-sidebar-product');
  if (!wrap) return;
  wrap.innerHTML = `
    <div class="cal-sidebar-product__logo" style="background:#EFF6FF;color:${esc(p.color)}">${esc(p.initials)}</div>
    <div class="cal-sidebar-product__name">${esc(p.name)}</div>
    <div class="cal-sidebar-product__score">Score: ${esc(String(p.score))}/10</div>
    <div class="cal-sidebar-product__best">${esc(p.bestFor)}</div>
    <a href="${esc(p.ctaUrl)}" class="cal-btn cal-btn--primary cal-sidebar-product__cta" rel="noopener">Visit ${esc(p.name)}</a>
  `;
}

/** 渲染 FAQ 列表 */
function calRenderFAQs() {
  const list = document.getElementById('cal-faq-list');
  if (!list) return;
  list.innerHTML = CAL_FAQS.map((faq, i) => `
    <div class="cal-faq-item" id="cal-faq-${i}">
      <button
        class="cal-faq-item__q"
        aria-expanded="false"
        aria-controls="cal-faq-a-${i}"
        id="cal-faq-q-${i}"
      >
        <span class="cal-faq-item__q-text">${esc(faq.q)}</span>
        <svg class="cal-faq-item__chevron" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div
        class="cal-faq-item__a"
        id="cal-faq-a-${i}"
        role="region"
        aria-labelledby="cal-faq-q-${i}"
        aria-hidden="true"
      >
        <div class="cal-faq-item__a-inner">
          <p>${esc(faq.a)}</p>
        </div>
      </div>
    </div>
  `).join('');
  calInitFAQAccordion();
}

/** 渲染相关分类 */
function calRenderRelated() {
  const grid = document.getElementById('cal-related-grid');
  if (!grid) return;
  grid.innerHTML = CAL_RELATED.map(cat => `
    <a href="${esc(cat.href)}" class="cal-related-card">
      <div class="cal-related-card__icon" aria-hidden="true">${CAL_ICON_MAP[cat.icon] || ''}</div>
      <p class="cal-related-card__name">${esc(cat.name)}</p>
      <p class="cal-related-card__count">${esc(cat.count)}</p>
      <span class="cal-related-card__arrow">Explore →</span>
    </a>
  `).join('');
}

/* ==========================================================================
   文案版本切换
   ========================================================================== */

/** 将指定版本的文案应用到页面中所有 data-cal-content 元素 */
function calApplyVersion(version) {
  const data = CAL_CONTENT[version];
  if (!data) return;
  calCurrentVersion = version;

  // 更新简单文本节点
  document.querySelectorAll('[data-cal-content]').forEach(el => {
    const key = el.getAttribute('data-cal-content');
    if (!data[key]) return;
    const val = data[key];
    // 数组类型交由专用渲染函数处理
    if (Array.isArray(val)) return;
    el.textContent = val;
  });

  // 更新需要特殊渲染的数组字段
  if (data['hero-trust'])       calRenderTrust(data['hero-trust']);
  if (data['hero-filters'])     calRenderFilters(data['hero-filters']);
  if (data['howwetest-items'])  calRenderHowWeTestItems(data['howwetest-items']);

  // 更新版本切换按钮状态
  document.querySelectorAll('.cal-dev-switcher__btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.version === version);
  });
}

/* ==========================================================================
   折叠动效（通用）
   高度动画：关闭→0px，打开→scrollHeight
   ========================================================================== */
function calToggleCollapsible(trigger, body) {
  const isOpen = trigger.getAttribute('aria-expanded') === 'true';
  if (isOpen) {
    body.style.height = body.scrollHeight + 'px';
    requestAnimationFrame(() => {
      body.style.height = '0px';
    });
    trigger.setAttribute('aria-expanded', 'false');
    body.setAttribute('aria-hidden', 'true');
  } else {
    body.style.height = '0px';
    trigger.setAttribute('aria-expanded', 'true');
    body.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
      body.style.height = body.scrollHeight + 'px';
    });
    // 展开完成后移除固定高度，允许内容自适应
    body.addEventListener('transitionend', function onEnd() {
      if (trigger.getAttribute('aria-expanded') === 'true') {
        body.style.height = 'auto';
      }
      body.removeEventListener('transitionend', onEnd);
    });
  }
}

/* ==========================================================================
   How We Test 折叠初始化
   ========================================================================== */
function calInitHowWeTest() {
  const trigger = document.getElementById('cal-howwetest-trigger');
  const body = document.getElementById('cal-howwetest-body');
  if (!trigger || !body) return;
  trigger.addEventListener('click', () => calToggleCollapsible(trigger, body));
}

/* ==========================================================================
   #4–5 小卡片折叠初始化
   ========================================================================== */
function calInitMinorPicks() {
  const trigger = document.getElementById('cal-picks-minor-trigger');
  const body = document.getElementById('cal-picks-minor');
  if (!trigger || !body) return;
  trigger.addEventListener('click', () => {
    const isOpen = trigger.getAttribute('aria-expanded') === 'true';
    calToggleCollapsible(trigger, body);
    trigger.querySelector('.cal-picks-minor-trigger__text').textContent =
      isOpen ? 'Show More Products (#4–5)' : 'Show Less';
  });
}

/* ==========================================================================
   FAQ Accordion 初始化
   ========================================================================== */
function calInitFAQAccordion() {
  const items = document.querySelectorAll('.cal-faq-item');
  items.forEach(item => {
    const q = item.querySelector('.cal-faq-item__q');
    const a = item.querySelector('.cal-faq-item__a');
    if (!q || !a) return;
    q.addEventListener('click', () => {
      const isOpen = q.getAttribute('aria-expanded') === 'true';
      // 折叠其他已开启的条目（单开模式）
      items.forEach(other => {
        if (other === item) return;
        const otherQ = other.querySelector('.cal-faq-item__q');
        const otherA = other.querySelector('.cal-faq-item__a');
        if (otherQ && otherA && otherQ.getAttribute('aria-expanded') === 'true') {
          otherA.style.height = otherA.scrollHeight + 'px';
          requestAnimationFrame(() => { otherA.style.height = '0px'; });
          otherQ.setAttribute('aria-expanded', 'false');
          otherA.setAttribute('aria-hidden', 'true');
          other.classList.remove('is-open');
        }
      });
      // 切换当前
      if (isOpen) {
        a.style.height = a.scrollHeight + 'px';
        requestAnimationFrame(() => { a.style.height = '0px'; });
        q.setAttribute('aria-expanded', 'false');
        a.setAttribute('aria-hidden', 'true');
        item.classList.remove('is-open');
      } else {
        a.style.height = '0px';
        q.setAttribute('aria-expanded', 'true');
        a.setAttribute('aria-hidden', 'false');
        item.classList.add('is-open');
        requestAnimationFrame(() => { a.style.height = a.scrollHeight + 'px'; });
        a.addEventListener('transitionend', function onEnd() {
          if (q.getAttribute('aria-expanded') === 'true') a.style.height = 'auto';
          a.removeEventListener('transitionend', onEnd);
        });
      }
    });
  });
}

/* ==========================================================================
   Sticky Anchor Nav —— 滚动高亮 + 吸顶检测
   ========================================================================== */
function calInitAnchorNav() {
  const nav = document.getElementById('cal-anchor-nav');
  const links = nav ? nav.querySelectorAll('.cal-anchor-nav__link') : [];
  const header = document.getElementById('cal-header');

  if (!nav || !links.length) return;

  // 计算锚点对应的 section 元素
  const sections = [];
  links.forEach(link => {
    const targetId = link.getAttribute('data-target');
    if (targetId) {
      const sec = document.getElementById(targetId);
      if (sec) sections.push({ link, sec });
    }
  });

  // 吸顶后添加 is-stuck class
  const navTop = nav.getBoundingClientRect().top + window.scrollY;
  const observerSticky = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      nav.classList.toggle('is-stuck', !entry.isIntersecting);
    });
  }, { threshold: 0, rootMargin: `-${Math.round(navTop)}px 0px 0px 0px` });

  // 用一个占位 sentinel 元素来检测吸顶
  const sentinel = document.createElement('div');
  sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;';
  nav.parentNode.insertBefore(sentinel, nav);
  observerSticky.observe(sentinel);

  // IntersectionObserver 高亮当前 section
  const offset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--cal-anchor-scroll-margin')) || 110;
  const sectionObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const activeLink = nav.querySelector(`.cal-anchor-nav__link[data-target="${entry.target.id}"]`);
        if (activeLink) {
          links.forEach(l => l.classList.remove('is-active'));
          activeLink.classList.add('is-active');
          // 保证激活项可见（移动端横向滚动）
          activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
      }
    });
  }, { rootMargin: `-${offset}px 0px -40% 0px`, threshold: 0 });

  sections.forEach(({ sec }) => sectionObserver.observe(sec));

  // 平滑锚点跳转（处理 scroll-margin-top 兼容性）
  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const targetId = link.getAttribute('data-target');
      const sec = document.getElementById(targetId);
      if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

/* ==========================================================================
   移动端汉堡菜单
   ========================================================================== */
function calInitMobileMenu() {
  const btn = document.getElementById('cal-hamburger');
  const menu = document.getElementById('cal-mobile-menu');
  if (!btn || !menu) return;
  btn.addEventListener('click', () => {
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', String(!isOpen));
    btn.classList.toggle('is-open', !isOpen);
    menu.classList.toggle('is-open', !isOpen);
    menu.setAttribute('aria-hidden', String(isOpen));
  });
  // 点击菜单项后关闭
  menu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      btn.setAttribute('aria-expanded', 'false');
      btn.classList.remove('is-open');
      menu.classList.remove('is-open');
      menu.setAttribute('aria-hidden', 'true');
    });
  });
}

/* ==========================================================================
   DEV 版本切换控件初始化
   ========================================================================== */
function calInitDevSwitcher() {
  const switcher = document.getElementById('cal-dev-switcher');
  const toggle = document.getElementById('cal-dev-toggle');
  if (!switcher) return;

  // 切换版本
  switcher.querySelectorAll('.cal-dev-switcher__btn').forEach(btn => {
    btn.addEventListener('click', () => {
      calApplyVersion(btn.dataset.version);
    });
  });

  // 隐藏/显示工具栏
  if (toggle) {
    toggle.addEventListener('click', () => {
      switcher.classList.toggle('is-hidden');
    });
  }
}

/* ==========================================================================
   Newsletter 表单（简单前端校验）
   ========================================================================== */
function calInitNewsletter() {
  const form = document.querySelector('.cal-newsletter__form');
  if (!form) return;
  form.addEventListener('submit', e => {
    e.preventDefault();
    const input = form.querySelector('.cal-newsletter__input');
    if (!input) return;
    if (!input.value || !input.value.includes('@')) {
      input.style.borderColor = '#DC2626';
      input.focus();
      return;
    }
    const btn = form.querySelector('.cal-newsletter__btn');
    if (btn) {
      btn.textContent = '✓ Subscribed!';
      btn.disabled = true;
      btn.style.background = '#059669';
      btn.style.borderColor = '#059669';
    }
  });
}

/* ==========================================================================
   初始化入口
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  // 1. 渲染静态产品数据
  calRenderComparisonTable(CAL_PRODUCTS);
  calRenderComparisonCards(CAL_PRODUCTS);
  calRenderFeaturedCard(CAL_PRODUCTS[0]);
  calRenderMidCards(CAL_PRODUCTS.slice(1, 3));
  calRenderMinorCards(CAL_PRODUCTS.slice(3));
  calRenderSidebarProduct(CAL_PRODUCTS[0]);
  calRenderFAQs();
  calRenderRelated();

  // 2. 应用默认文案版本（version-a）
  calApplyVersion('version-a');

  // 3. 初始化交互组件
  calInitHowWeTest();
  calInitMinorPicks();
  calInitAnchorNav();
  calInitMobileMenu();
  calInitDevSwitcher();
  calInitNewsletter();
});
