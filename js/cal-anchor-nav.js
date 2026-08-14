/**
 * cal-anchor-nav.js
 * ------------------------------------------------------------
 * CyberAtlasLab — Category Sticky Anchor Nav
 *
 * 功能：
 * 1. Desktop / Mobile 共用 Sticky 逻辑
 * 2. Desktop NAV 与 #primary 主内容列对齐
 * 3. Desktop NAV 右边界不会进入 #secondary Sidebar
 * 4. Mobile Contents 支持展开 / 收起
 * 5. 点击目录后先关闭 Mobile 面板，再计算滚动位置
 * 6. IntersectionObserver 当前章节高亮
 * 7. 到达最后一个 Anchor（FAQ）后 NAV 隐藏
 * 8. FAQ 以下不改变文档流，因此不会产生页面抖动
 * 9. 向上滚回 Hero 后正确解除 Sticky
 * 10. resize / breakpoint 自动重新计算
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* =========================================================
           0. DOM
           ========================================================= */

        var wrap = document.querySelector(
            '.cal-anchor-nav-wrap'
        );

        if (!wrap) {
            return;
        }

        var sentinel = wrap.querySelector(
            '.cal-anchor-nav__sentinel'
        );

        var deskNav = wrap.querySelector(
            '.cal-anchor-nav'
        );

        var mobileNav = wrap.querySelector(
            '.cal-anchor-nav-mobile'
        );

        if (!sentinel) {
            return;
        }

        var allLinks = Array.prototype.slice.call(
            wrap.querySelectorAll('[data-anchor]')
        );

        if (!allLinks.length) {
            return;
        }


        /* =========================================================
           1. 有效 Sections
           ========================================================= */

        var sections = [];
        var targetIdToLinks = {};
        var seen = {};

        allLinks.forEach(function (link) {

            var id = (
                link.getAttribute('data-anchor') || ''
            ).replace(/^#/, '');

            if (!id) {
                return;
            }

            var target = document.getElementById(id);

            if (!target) {
                return;
            }

            if (!targetIdToLinks[id]) {
                targetIdToLinks[id] = [];
            }

            targetIdToLinks[id].push(link);

            if (!seen[id]) {

                seen[id] = true;
                sections.push(target);
            }
        });

        if (!sections.length) {
            return;
        }


        /* =========================================================
           2. Responsive
           ========================================================= */

        function isMobile() {

            return window.matchMedia(
                '(max-width: 1024px)'
            ).matches;
        }


        function getActiveNav() {

            return isMobile()
                ? mobileNav
                : deskNav;
        }


        /* =========================================================
           3. Header Bottom
           ========================================================= */

        function getHeaderOffset() {

            var bottom = 0;

            var headers = document.querySelectorAll(
                '.bdt-sticky, .elementor-sticky, header'
            );

            Array.prototype.forEach.call(
                headers,
                function (el) {

                    var style =
                        window.getComputedStyle(el);

                    var rect =
                        el.getBoundingClientRect();

                    if (
                        (
                            style.position === 'fixed' ||
                            style.position === 'sticky'
                        ) &&
                        rect.top <= 2 &&
                        rect.bottom > 0
                    ) {

                        bottom = Math.max(
                            bottom,
                            rect.bottom
                        );
                    }
                }
            );


            /* WordPress Admin Bar */
            var adminBar =
                document.getElementById('wpadminbar');

            if (adminBar) {

                var adminStyle =
                    window.getComputedStyle(adminBar);

                if (
                    adminStyle.position === 'fixed'
                ) {

                    bottom = Math.max(
                        bottom,
                        adminBar
                            .getBoundingClientRect()
                            .bottom
                    );
                }
            }

            return Math.max(
                0,
                Math.round(bottom)
            );
        }


        /**
 * =============================================================
 * 4. 获取 Desktop NAV 收缩后的主体内容区域
 *
 * 核心原则：
 *
 * ① 左边界：
 *    使用 sentinel 的 left
 *
 * ② 右边界：
 *    优先使用 #guide 的实际 right
 *
 *    不再使用：
 *
 *        sidebar.left - 8
 *
 *    因为 Sidebar 与 Main Content 之间存在 column gap，
 *    用 Sidebar 反推会导致 NAV 右边界向右偏移。
 *
 * ③ fallback：
 *    如果 #guide 不存在，再使用 #primary
 *
 * ④ 最终：
 *    如果都无法获得有效宽度，则返回 null，
 *    由上层逻辑继续使用全宽状态。
 * =============================================================
 */
        function getMainContentRect() {

            /*
             * =========================================================
             * 1. 左边界
             * =========================================================
             *
             * Sentinel 与 NAV 位于同一个 wrapper，
             * 因此 sentinel.left 是当前页面主体左边界。
             */
            var sentinelRect =
                sentinel.getBoundingClientRect();

            var left =
                sentinelRect.left;


            /*
             * =========================================================
             * 2. 优先使用 #guide
             * =========================================================
             *
             * Buying Guide 是当前页面真正进入：
             *
             * Main Content + Sidebar
             *
             * 双栏结构的主体内容区域。
             *
             * 从实际页面测量来看：
             *
             * #guide
             * left  ≈ 53px
             * right ≈ 901px
             * width ≈ 848px
             *
             * 因此 NAV 收缩态应该严格跟随 #guide 的
             * 实际几何边界。
             */
            var guide =
                document.getElementById('guide');

            if (guide) {

                var guideRect =
                    guide.getBoundingClientRect();

                var guideWidth =
                    guideRect.right - left;


                /*
                 * 防止异常 DOM / 响应式状态导致错误宽度。
                 */
                if (guideWidth > 200) {

                    return {
                        left: left,
                        right: guideRect.right,
                        width: guideWidth
                    };
                }
            }


            /*
             * =========================================================
             * 3. #guide 不存在时使用 #primary
             * =========================================================
             *
             * 这是安全 fallback。
             *
             * 不再通过 Sidebar 左边界反推 Main Content，
             * 避免 column gap 被错误计算进 NAV 宽度。
             */
            var primary =
                document.querySelector('#primary');

            if (primary) {

                var primaryRect =
                    primary.getBoundingClientRect();

                var primaryWidth =
                    primaryRect.right - left;


                if (primaryWidth > 200) {

                    return {
                        left: left,
                        right: primaryRect.right,
                        width: primaryWidth
                    };
                }
            }


            /*
             * =========================================================
             * 4. 最终 fallback
             * =========================================================
             *
             * 找不到可靠的主体内容边界时，
             * 不返回错误尺寸。
             *
             * 上层逻辑会保持当前全宽状态。
             */
            return null;
        }

        /**
         * 判断 NAV 当前是否进入「Buying Guide + Sidebar」双栏区域
         *
         * 页面实际结构：
         *
         * Comparison      → 1200px
         * How We Test     → 1200px
         * Top Picks       → 1200px
         * Buying Guide    → 848px + Sidebar
         * What We Compare → 1200px
         * FAQ             → 1200px
         *
         * 因此 #guide 才是 Desktop NAV 收缩的真实垂直边界。
         */
        function isInsideMainContentArea() {

            /*
             * NAV 提前进入主体双栏态的缓冲距离。
             *
             * Buying Guide 的顶部真正进入 Header 下方之前，
             * NAV 提前 120px 收缩到左侧 Main Content，
             * 避免 Sidebar 已经出现后 NAV 仍保持全宽。
             */
            var MAIN_CONTENT_EARLY_OFFSET = 120;

            var guide =
                document.getElementById('guide');

            if (!guide) {
                return false;
            }

            var rect =
                guide.getBoundingClientRect();

            var scrollY =
                window.pageYOffset ||
                document.documentElement.scrollTop ||
                0;

            var guideTop =
                rect.top + scrollY;

            var guideBottom =
                rect.bottom + scrollY;

            var headerOffset =
                getHeaderOffset();


            /*
             * NAV 进入 Buying Guide 区域时开始收缩。
             *
             * 使用 Header 下缘作为 Sticky 参考线，
             * 避免刚进入区域时产生 1~2px 边界抖动。
             */
            var start =
                guideTop - headerOffset - MAIN_CONTENT_EARLY_OFFSET;


            /*
             * Buying Guide 结束后立即恢复全宽。
             */
            var end =
                guideBottom - headerOffset;


            return (
                scrollY >= start &&
                scrollY < end
            );
        }


        /* =========================================================
           5. Desktop Position
           ========================================================= */

        function positionDesktopNav() {

            if (
                !deskNav ||
                !deskNav.classList.contains('is-stuck')
            ) {
                return;
            }


            /*
             * =========================================================
             * 主体内容区域内
             * ---------------------------------------------------------
             * NAV 收缩到 Main Content，避开 Sidebar。
             * =========================================================
             */

            if (isInsideMainContentArea()) {

                var rect =
                    getMainContentRect();

                if (!rect) {
                    return;
                }

                deskNav.style.left =
                    Math.round(rect.left) + 'px';

                deskNav.style.width =
                    Math.round(rect.width) + 'px';

                return;
            }


            /*
             * =========================================================
             * 主体内容区域之外
             * ---------------------------------------------------------
             * NAV 恢复原始全宽。
             *
             * Sentinel 是 NAV 原本在文档流中的几何宽度，
             * 因此用它恢复最准确。
             * =========================================================
             */

            var fullRect =
                sentinel.getBoundingClientRect();

            deskNav.style.left =
                Math.round(fullRect.left) + 'px';

            deskNav.style.width =
                Math.round(fullRect.width) + 'px';
        }


        /* =========================================================
           6. Mobile Position
           ========================================================= */

        function positionMobileNav() {

            if (
                !mobileNav ||
                !mobileNav.classList.contains('is-stuck')
            ) {
                return;
            }

            var rect =
                wrap.getBoundingClientRect();

            mobileNav.style.left =
                Math.round(rect.left) + 'px';

            mobileNav.style.width =
                Math.round(rect.width) + 'px';

            mobileNav.style.top =
                getHeaderOffset() + 'px';
        }


        /* =========================================================
           7. Nav Outer Height
           ========================================================= */

        function getOuterHeight(el) {

            if (!el) {
                return 0;
            }

            var style =
                window.getComputedStyle(el);

            var marginTop =
                parseFloat(style.marginTop) || 0;

            var marginBottom =
                parseFloat(style.marginBottom) || 0;

            return Math.ceil(
                el.getBoundingClientRect().height +
                marginTop +
                marginBottom
            );
        }


        /* =========================================================
           8. Sentinel Height
           ---------------------------------------------------------
           Sticky 时必须保留 NAV 原本占据的空间。
           FAQ 结束时也不能把高度突然变回 1px，
           否则会造成页面跳动。
           ========================================================= */

        var originalSentinelHeight =
            Math.max(
                1,
                sentinel.offsetHeight
            );


        function updateSentinelHeight() {

            var activeNav =
                getActiveNav();

            if (
                !activeNav ||
                !activeNav.classList.contains(
                    'is-stuck'
                )
            ) {

                sentinel.style.height =
                    originalSentinelHeight + 'px';

                return;
            }

            sentinel.style.height =
                getOuterHeight(activeNav) + 'px';
        }


        /* =========================================================
           9. Hide / Show Sticky Nav
           ---------------------------------------------------------
           FAQ 以下：
             不解除 fixed
             不改变 sentinel
             只隐藏视觉层

           这样完全不会造成 CLS / 抖动。
           ========================================================= */

        function hideStickyNav() {

            var activeNav =
                getActiveNav();

            if (!activeNav) {
                return;
            }

            activeNav.style.visibility =
                'hidden';

            activeNav.style.pointerEvents =
                'none';
        }


        function showStickyNav() {

            var activeNav =
                getActiveNav();

            if (!activeNav) {
                return;
            }

            activeNav.style.visibility =
                '';

            activeNav.style.pointerEvents =
                '';
        }


        /* =========================================================
           10. Sticky State
           ========================================================= */

        var stickyMode = null;


        function setSticky(stuck) {

            var mobile =
                isMobile();

            var mode =
                mobile
                    ? 'mobile'
                    : 'desktop';


            /*
             * ===============================
             * 解除 Sticky
             * ===============================
             */
            if (!stuck) {

                stickyMode = null;

                if (deskNav) {

                    deskNav.classList.remove(
                        'is-stuck'
                    );

                    deskNav.style.left = '';
                    deskNav.style.width = '';
                    deskNav.style.top = '';
                    deskNav.style.visibility = '';
                    deskNav.style.pointerEvents = '';
                    deskNav.style.position = '';
                }


                if (mobileNav) {

                    mobileNav.classList.remove(
                        'is-stuck'
                    );

                    mobileNav.style.left = '';
                    mobileNav.style.width = '';
                    mobileNav.style.top = '';
                    mobileNav.style.visibility = '';
                    mobileNav.style.pointerEvents = '';
                    mobileNav.style.position = '';
                }


                sentinel.style.height =
                    originalSentinelHeight + 'px';

                return;
            }


            /*
             * 已经是当前模式：
             * 不重新切换 DOM 状态。
             */
            if (
                stickyMode === mode
            ) {

                showStickyNav();

                if (mobile) {

                    positionMobileNav();

                } else {

                    positionDesktopNav();
                }

                updateSentinelHeight();

                return;
            }


            stickyMode = mode;


            /*
             * 清除另一端。
             */
            if (deskNav) {

                deskNav.classList.remove(
                    'is-stuck'
                );

                deskNav.style.left = '';
                deskNav.style.width = '';
                deskNav.style.top = '';
                deskNav.style.visibility = '';
                deskNav.style.pointerEvents = '';
                deskNav.style.position = '';
            }


            if (mobileNav) {

                mobileNav.classList.remove(
                    'is-stuck'
                );

                mobileNav.style.left = '';
                mobileNav.style.width = '';
                mobileNav.style.top = '';
                mobileNav.style.visibility = '';
                mobileNav.style.pointerEvents = '';
                mobileNav.style.position = '';
            }


            var activeNav =
                mobile
                    ? mobileNav
                    : deskNav;

            if (!activeNav) {
                return;
            }


            activeNav.classList.add(
                'is-stuck'
            );


            /*
             * Mobile CSS 当前没有可靠的
             * .is-stuck position 定义时，
             * JS 直接保证 fixed。
             */
            if (mobile) {

                activeNav.style.position =
                    'fixed';

                positionMobileNav();

            } else {

                positionDesktopNav();
            }


            showStickyNav();

            updateSentinelHeight();
        }


        /* =========================================================
           11. FAQ Boundary
           ========================================================= */

        function isPastLastSection() {

            var activeNav =
                getActiveNav();

            var lastSection =
                sections[sections.length - 1];

            if (
                !activeNav ||
                !lastSection ||
                !activeNav.classList.contains(
                    'is-stuck'
                )
            ) {
                return false;
            }


            var stickyBottom =
                getHeaderOffset() +
                activeNav.offsetHeight;


            var lastBottom =
                lastSection
                    .getBoundingClientRect()
                    .bottom;


            return (
                lastBottom <= stickyBottom
            );
        }


        /* =========================================================
           12. Sticky Scroll State
           ========================================================= */

        var ticking = false;


        function updateStickyState() {

            ticking = false;

            var activeNav =
                getActiveNav();

            if (!activeNav) {
                return;
            }


            var headerBottom =
                getHeaderOffset();

            var sentinelTop =
                sentinel
                    .getBoundingClientRect()
                    .top;


            /*
             * =====================================================
             * A. 已经 Sticky
             * =====================================================
             */

            if (
                activeNav.classList.contains(
                    'is-stuck'
                )
            ) {

                /*
                 * ① 向上滚回 Hero：
                 * Sentinel 已经重新进入 Header 以下，
                 * 必须彻底解除 Sticky。
                 */
                if (
                    sentinelTop > headerBottom
                ) {

                    setSticky(false);

                    return;
                }


                /*
                 * ② FAQ 以下：
                 * 只隐藏 NAV，不解除 Sticky。
                 *
                 * 这样 sentinel 高度继续保留，
                 * 页面不会发生跳动。
                 */
                if (
                    isPastLastSection()
                ) {

                    hideStickyNav();

                } else {

                    showStickyNav();
                }


                /*
                 * ③ 保持位置
                 */
                if (isMobile()) {

                    positionMobileNav();

                } else {

                    positionDesktopNav();
                }

                return;
            }


            /*
             * =====================================================
             * B. 当前没有 Sticky
             * =====================================================
             */

            if (
                sentinelTop <= headerBottom
            ) {

                setSticky(true);
            }
        }


        function requestStickyUpdate() {

            if (ticking) {
                return;
            }

            ticking = true;

            window.requestAnimationFrame(
                updateStickyState
            );
        }


        window.addEventListener(
            'scroll',
            requestStickyUpdate,
            {
                passive: true
            }
        );


        /* =========================================================
           13. Active Target
           ========================================================= */

        var activeId = null;


        function setActiveTarget(id) {

            if (
                !id ||
                activeId === id
            ) {
                return;
            }

            activeId = id;

            allLinks.forEach(
                function (link) {

                    var linkId =
                        (
                            link.getAttribute(
                                'data-anchor'
                            ) || ''
                        ).replace(/^#/, '');

                    if (linkId === id) {

                        link.classList.add(
                            'is-active'
                        );

                        link.setAttribute(
                            'aria-current',
                            'true'
                        );

                    } else {

                        link.classList.remove(
                            'is-active'
                        );

                        link.removeAttribute(
                            'aria-current'
                        );
                    }
                }
            );
        }


        /* =========================================================
           14. Scroll Offset
           ========================================================= */

        function getScrollOffset() {

            var offset =
                getHeaderOffset();

            var activeNav =
                getActiveNav();

            if (
                activeNav &&
                activeNav.classList.contains(
                    'is-stuck'
                )
            ) {

                offset +=
                    activeNav.offsetHeight;
            }

            return offset + 12;
        }


        /* =========================================================
           15. IntersectionObserver
           ========================================================= */

        var observer = null;


        function buildObserver() {

            if (observer) {

                observer.disconnect();
            }

            var offset =
                getScrollOffset();

            var ratios = {};


            observer =
                new IntersectionObserver(
                    function (entries) {

                        entries.forEach(
                            function (entry) {

                                ratios[
                                    entry.target.id
                                ] =
                                    entry.isIntersecting
                                        ? entry.intersectionRatio
                                        : 0;
                            }
                        );


                        var bestId = null;
                        var bestRatio = 0;


                        sections.forEach(
                            function (section) {

                                var ratio =
                                    ratios[
                                    section.id
                                    ] || 0;

                                if (
                                    ratio >
                                    bestRatio
                                ) {

                                    bestRatio =
                                        ratio;

                                    bestId =
                                        section.id;
                                }
                            }
                        );


                        if (bestId) {

                            setActiveTarget(
                                bestId
                            );
                        }
                    },
                    {
                        root: null,

                        rootMargin:
                            '-' +
                            offset +
                            'px 0px -55% 0px',

                        threshold: [
                            0,
                            0.25,
                            0.5,
                            0.75,
                            1
                        ]
                    }
                );


            sections.forEach(
                function (section) {

                    observer.observe(
                        section
                    );
                }
            );
        }


        setActiveTarget(
            sections[0].id
        );

        buildObserver();


        /* =========================================================
           16. Mobile Contents
           ========================================================= */

        var toggle =
            mobileNav
                ? mobileNav.querySelector(
                    '.cal-anchor-nav-mobile__toggle'
                )
                : null;

        var list =
            mobileNav
                ? mobileNav.querySelector(
                    '.cal-anchor-nav-mobile__list'
                )
                : null;


        function openMobilePanel() {

            if (
                !mobileNav ||
                !toggle ||
                !list
            ) {
                return;
            }

            mobileNav.classList.add(
                'is-open'
            );

            toggle.setAttribute(
                'aria-expanded',
                'true'
            );

            list.hidden = false;

            list.setAttribute(
                'aria-hidden',
                'false'
            );


            updateSentinelHeight();
        }


        function closeMobilePanel() {

            if (
                !mobileNav ||
                !toggle ||
                !list
            ) {
                return;
            }

            mobileNav.classList.remove(
                'is-open'
            );

            toggle.setAttribute(
                'aria-expanded',
                'false'
            );

            list.hidden = true;

            list.setAttribute(
                'aria-hidden',
                'true'
            );


            updateSentinelHeight();
        }


        if (
            toggle &&
            mobileNav
        ) {

            toggle.addEventListener(
                'click',
                function (e) {

                    e.stopPropagation();

                    if (
                        mobileNav.classList.contains(
                            'is-open'
                        )
                    ) {

                        closeMobilePanel();

                    } else {

                        openMobilePanel();
                    }
                }
            );


            document.addEventListener(
                'click',
                function (e) {

                    if (
                        !mobileNav.classList.contains(
                            'is-open'
                        )
                    ) {
                        return;
                    }

                    if (
                        mobileNav.contains(
                            e.target
                        )
                    ) {
                        return;
                    }

                    closeMobilePanel();
                }
            );


            document.addEventListener(
                'keydown',
                function (e) {

                    if (
                        e.key === 'Escape'
                    ) {

                        closeMobilePanel();
                    }
                }
            );


            /*
             * 页面滚动自动关闭。
             */
            window.addEventListener(
                'scroll',
                function () {

                    if (
                        mobileNav.classList.contains(
                            'is-open'
                        )
                    ) {

                        closeMobilePanel();
                    }
                },
                {
                    passive: true
                }
            );
        }


        /* =========================================================
           17. Anchor Click
           ========================================================= */

        allLinks.forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function (e) {

                        var id =
                            (
                                link.getAttribute(
                                    'data-anchor'
                                ) || ''
                            ).replace(/^#/, '');

                        var target =
                            document.getElementById(
                                id
                            );

                        if (!target) {
                            return;
                        }

                        e.preventDefault();


                        /*
                         * Mobile：
                         * 先关闭展开面板。
                         *
                         * 这样 offset 不会再包含
                         * 约 300px 的 Contents 高度。
                         */
                        if (
                            mobileNav &&
                            mobileNav.classList.contains(
                                'is-open'
                            )
                        ) {

                            closeMobilePanel();
                        }


                        updateStickyState();


                        var offset =
                            getScrollOffset();


                        var top =
                            target
                                .getBoundingClientRect()
                                .top +
                            window.pageYOffset -
                            offset;


                        window.scrollTo({
                            top: Math.max(
                                0,
                                Math.round(top)
                            ),
                            behavior: 'smooth'
                        });


                        setActiveTarget(id);


                        history.pushState(
                            null,
                            '',
                            '#' + id
                        );
                    }
                );
            }
        );


        /* =========================================================
           18. Resize
           ========================================================= */

        var resizeTimer = null;


        window.addEventListener(
            'resize',
            function () {

                clearTimeout(
                    resizeTimer
                );

                resizeTimer =
                    setTimeout(
                        function () {

                            /*
                             * 收起 Mobile。
                             */
                            closeMobilePanel();


                            /*
                             * 清除 Sticky。
                             */
                            setSticky(false);


                            /*
                             * 重新记录 Sentinel 原始高度。
                             */
                            sentinel.style.height =
                                '';

                            originalSentinelHeight =
                                Math.max(
                                    1,
                                    sentinel.offsetHeight
                                );


                            /*
                             * Observer 重建。
                             */
                            buildObserver();


                            /*
                             * 重新检测 Sticky。
                             */
                            requestStickyUpdate();

                        },
                        100
                    );
            }
        );


        /* =========================================================
           19. Initial
           ========================================================= */

        requestStickyUpdate();

    });

})();
