/**
 * cal-anchor-nav.js
 * ------------------------------------------------------------
 * 分类页 Sticky Anchor Nav（[cal_anchor_nav]）统一交互脚本。
 *
 * 职责（单文件，对齐仓库 toc-highlight.js 的"一份文件覆盖桌面+移动"惯例）：
 * 1. 滚动高亮：IntersectionObserver 判定当前所在分段，同步点亮桌面 Tab
 *    下划线与移动折叠条内的编号列表项，并更新移动折叠条的动态标题。
 * 2. 折叠交互：移动端折叠条展开/收起，交互与 [crs_toc_mobile] 对齐
 *    （点击触发、点击外部关闭、Esc 关闭、选中后自动收起）。
 * 3. 吸顶态：监听哨兵元素，滚动经过原始位置后追加 is-stuck，只加阴影，
 *    不改变布局。
 *
 * 渐进增强：HTML 层所有链接始终可点击、可被抓取；本脚本只负责状态展示。
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var nav = document.querySelector('[data-cal-anchor-nav]');
        if (!nav) {
            return;
        }

        var desktopTabs = Array.prototype.slice.call(
            nav.querySelectorAll('.cal-anchor-nav__tab[data-anchor-target]')
        );
        var mobileItems = Array.prototype.slice.call(
            nav.querySelectorAll('.cal-anchor-nav__item[data-anchor-target]')
        );
        var allLinks = desktopTabs.concat(mobileItems);

        if (!allLinks.length) {
            return;
        }

        var mobileWrap = nav.querySelector('.cal-anchor-nav__mobile');
        var toggle = nav.querySelector('.cal-anchor-nav__toggle');
        var list = nav.querySelector('.cal-anchor-nav__list');
        var currentLabelEl = nav.querySelector('[data-cal-anchor-current-label]');

        /* =====================================================
           A. 动态头部偏移（与 toc-highlight.js 同一测量逻辑，
              避免吸顶导航条 + 站点自身 Header 叠加后遮挡分段标题）
           ===================================================== */
        function getHeaderOffset() {
            var header = document.querySelector('.bdt-sticky, .elementor-sticky, header');
            var offset = header ? header.offsetHeight : 0;

            var adminBar = document.getElementById('wpadminbar');
            if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                offset += adminBar.offsetHeight;
            }

            offset += nav.offsetHeight;
            return offset;
        }

        /* =====================================================
           B. 滚动高亮（IntersectionObserver）
           ===================================================== */
        var targetIdToLinks = {};
        var sections = [];
        var seen = {};

        allLinks.forEach(function (link) {
            var selector = link.getAttribute('data-anchor-target');
            var el = selector ? document.querySelector(selector) : null;
            if (!el) {
                return; // 页面未包含该模块（比如没有 FAQ），静默跳过，不报错
            }
            var id = el.id;
            if (!targetIdToLinks[id]) {
                targetIdToLinks[id] = [];
            }
            targetIdToLinks[id].push(link);

            if (!seen[id]) {
                seen[id] = true;
                sections.push(el);
            }
        });

        if (!sections.length) {
            return;
        }

        function setActiveTarget(id) {
            var links = targetIdToLinks[id];
            if (!links || !links.length) {
                return;
            }

            allLinks.forEach(function (link) {
                link.classList.remove('is-active');
                link.setAttribute('aria-current', 'false');
            });

            links.forEach(function (link) {
                link.classList.add('is-active');
                link.setAttribute('aria-current', 'true');
            });

            if (currentLabelEl) {
                var labelEl = links[0].querySelector('.cal-anchor-nav__item-label');
                currentLabelEl.textContent = labelEl
                    ? labelEl.textContent.trim()
                    : links[0].textContent.trim();
            }
        }

        var ratios = {};
        function pickBestId() {
            var bestId = null;
            var bestRatio = 0;
            Object.keys(ratios).forEach(function (id) {
                if (ratios[id] > bestRatio) {
                    bestRatio = ratios[id];
                    bestId = id;
                }
            });
            return bestId;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                ratios[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
            });
            var bestId = pickBestId();
            if (bestId) {
                setActiveTarget(bestId);
            }
        }, {
            root: null,
            rootMargin: '-' + getHeaderOffset() + 'px 0px -55% 0px',
            threshold: [0, 0.25, 0.5, 0.75, 1]
        });

        sections.forEach(function (el) {
            observer.observe(el);
        });

        setActiveTarget(sections[0].id);

        /* =====================================================
           C. 平滑滚动跳转
           ===================================================== */
        allLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var selector = link.getAttribute('data-anchor-target');
                var target = selector ? document.querySelector(selector) : null;
                if (!target) {
                    return;
                }
                e.preventDefault();

                var top = target.getBoundingClientRect().top + window.pageYOffset - getHeaderOffset() - 12;
                window.scrollTo({ top: top, behavior: 'smooth' });
                setActiveTarget(target.id);
                history.pushState(null, null, '#' + target.id);

                if (mobileWrap && mobileWrap.classList.contains('is-open')) {
                    closeMobilePanel();
                }
            });
        });

        /* =====================================================
           D. 移动端折叠交互（对齐 [crs_toc_mobile] 行为）
           ===================================================== */
        function openMobilePanel() {
            if (!mobileWrap || mobileWrap.classList.contains('is-open')) {
                return;
            }
            mobileWrap.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            if (list) {
                list.setAttribute('aria-hidden', 'false');
            }
        }

        function closeMobilePanel() {
            if (!mobileWrap || !mobileWrap.classList.contains('is-open')) {
                return;
            }
            mobileWrap.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            if (list) {
                list.setAttribute('aria-hidden', 'true');
            }
        }

        if (toggle && mobileWrap) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (mobileWrap.classList.contains('is-open')) {
                    closeMobilePanel();
                } else {
                    openMobilePanel();
                }
            });

            document.addEventListener('click', function (e) {
                if (!mobileWrap.classList.contains('is-open')) {
                    return;
                }
                if (mobileWrap.contains(e.target)) {
                    return;
                }
                closeMobilePanel();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeMobilePanel();
                }
            });
        }

        /* =====================================================
           E. 吸顶态（哨兵元素）
           ===================================================== */
        var sentinel = nav.previousElementSibling;
        if (
            sentinel &&
            sentinel.classList.contains('cal-anchor-sentinel') &&
            'IntersectionObserver' in window
        ) {
            var stuckObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    nav.classList.toggle('is-stuck', !entry.isIntersecting);
                });
            });
            stuckObserver.observe(sentinel);
        }
    });
})();
