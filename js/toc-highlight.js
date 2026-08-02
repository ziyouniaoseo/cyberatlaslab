/**
 * CRS TOC v4.2 - 双端显式分流版
 */
(function () {
    'use strict';

    let desktopLock = false;
    let desktopScrollTimeout = null;

    document.addEventListener('DOMContentLoaded', function () {

        function getDynamicHeaderOffset() {
            const stickyHeader = document.querySelector('.bdt-sticky, .elementor-sticky, header');
            let offset = stickyHeader ? stickyHeader.offsetHeight : 0;

            const adminBar = document.getElementById('wpadminbar');
            if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                offset += adminBar.offsetHeight;
            }

            return offset > 0 ? offset : 80;
        }

        // =================================================================
        // 【A】桌面端 TOC
        // =================================================================
        const tocNav = document.querySelector('.crs-sidebar__toc[data-context="desktop"]');
        if (tocNav) {
            const tocLinks = tocNav.querySelectorAll('.crs-sidebar__toc-link[data-context="desktop"]');
            const scroller = tocNav.querySelector('.crs-sidebar__toc-list');

            if (tocLinks.length && scroller) {
                const sections = [];
                tocLinks.forEach(function (link) {
                    const targetId = link.getAttribute('data-target') || link.getAttribute('href').substring(1);
                    const section = document.getElementById(targetId);
                    if (section) sections.push({ id: targetId, element: section, link: link });
                });

                scroller.addEventListener('wheel', function (e) {
                    const scrollHeight = this.scrollHeight;
                    const clientHeight = this.clientHeight;
                    if (scrollHeight <= clientHeight) return;

                    const atTop = this.scrollTop <= 0 && e.deltaY < 0;
                    const atBottom = this.scrollTop + clientHeight >= scrollHeight - 1 && e.deltaY > 0;

                    if (!atTop && !atBottom) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.scrollTop += e.deltaY;
                    }
                }, { passive: false });

                tocLinks.forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();

                        const targetId = this.getAttribute('data-target') || this.getAttribute('href').substring(1);
                        const target = document.getElementById(targetId);
                        if (!target) return;

                        desktopLock = true;
                        clearTimeout(desktopScrollTimeout);
                        setActiveLink(targetId, true);

                        const headerOffset = getDynamicHeaderOffset() + 20;
                        const offsetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;

                        window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                        history.pushState(null, null, '#' + targetId);

                        const verifyScrollEnd = () => {
                            if (Math.abs(window.pageYOffset - offsetPosition) < 4) {
                                desktopLock = false;
                            } else {
                                desktopScrollTimeout = setTimeout(verifyScrollEnd, 60);
                            }
                        };
                        desktopScrollTimeout = setTimeout(verifyScrollEnd, 150);
                    }, true);
                });

                function setActiveLink(activeId, immediate) {
                    let newlyActiveLink = null;
                    tocLinks.forEach(function (link) {
                        const target = link.getAttribute('data-target') || link.getAttribute('href').substring(1);
                        link.classList.toggle('is-active', target === activeId);
                        if (target === activeId) newlyActiveLink = link;
                    });

                    if (newlyActiveLink && scroller) {
                        newlyActiveLink.scrollIntoView({
                            behavior: immediate ? 'instant' : 'smooth',
                            block: 'nearest'
                        });
                    }
                }

                function onDesktopScroll() {
                    if (desktopLock || window.getComputedStyle(tocNav).display === 'none') return;

                    const scrollPos = window.pageYOffset + getDynamicHeaderOffset() + 30;
                    let current = null;

                    for (let i = 0; i < sections.length; i++) {
                        const absTop = sections[i].element.getBoundingClientRect().top + window.pageYOffset;
                        if (absTop <= scrollPos) {
                            current = sections[i];
                        }
                    }

                    if (current) {
                        const active = tocNav.querySelector('.crs-sidebar__toc-link.is-active');
                        const activeId = active ? (active.getAttribute('data-target') || active.getAttribute('href').substring(1)) : null;
                        if (activeId !== current.id) {
                            setActiveLink(current.id, false);
                        }
                    }
                }

                window.addEventListener('scroll', function () {
                    if (!desktopLock) {
                        requestAnimationFrame(onDesktopScroll);
                    }
                }, { passive: true });

                onDesktopScroll();
            }
        }

        // =================================================================
        // 【B】移动端 TOC (无障碍与触控防御升级版)
        // =================================================================
        const tocMobile = document.querySelector('.crs-toc-mobile[data-context="mobile"]');

        if (tocMobile) {
            const links = tocMobile.querySelectorAll(
                '.crs-toc-mobile__link[data-context="mobile"]'
            );

            const trigger = tocMobile.querySelector(
                '.crs-toc-mobile__trigger'
            );

            if (trigger && links.length) {
                /**
                 * 核心升级 1：动态获取被控制的 <ul> 面板
                 * 优先从触发器上的 aria-controls 读取绑定的面板 ID
                 */
                const listId = trigger.getAttribute('aria-controls');
                const tocList = listId ? document.getElementById(listId) : tocMobile.querySelector('.crs-toc-mobile__list');

                const sections = [];

                links.forEach(function (link) {
                    const targetId =
                        link.getAttribute('data-target') ||
                        link.getAttribute('href').substring(1);

                    const section = document.getElementById(targetId);

                    if (section) {
                        sections.push({
                            id: targetId,
                            element: section,
                            link: link
                        });
                    }
                });

                /**
                 * 获取动态头部偏移量
                 */
                function getHeaderOffset() {
                    if (typeof getDynamicHeaderOffset === 'function') {
                        return getDynamicHeaderOffset();
                    }
                    const header = document.querySelector('.site-header, #masthead, .ast-primary-header-bar');
                    const tocBar = document.querySelector('.crs-toc-mobile');
                    const headerH = header ? header.offsetHeight : 0;
                    const tocH = tocBar ? tocBar.offsetHeight : 0;
                    return headerH + tocH + 15;
                }

                /**
                 * 统一关闭TOC（增加了 ARIA 状态同步）
                 */
                function closeMobileToc() {
                    if (!tocMobile.classList.contains('is-open')) {
                        return;
                    }

                    tocMobile.classList.remove('is-open');

                    // 核心升级 2：关闭时，同步将状态声明为不可见
                    trigger.setAttribute('aria-expanded', 'false');
                    if (tocList) {
                        tocList.setAttribute('aria-hidden', 'true');
                    }
                }

                /**
                 * Trigger 点击/展开事件
                 */
                trigger.addEventListener('click', function (e) {
                    e.stopPropagation();

                    const isOpen = tocMobile.classList.toggle('is-open');

                    // 核心升级 3：点击切换时，两组 ARIA 状态像齿轮一样同步反转
                    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    if (tocList) {
                        tocList.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    }
                });

                /**
                 * 点击页面其它区域关闭
                 */
                document.addEventListener('click', function (e) {
                    if (!tocMobile.classList.contains('is-open')) {
                        return;
                    }

                    if (tocMobile.contains(e.target)) {
                        return;
                    }

                    closeMobileToc();
                });

                /**
                 * ESC 键关闭
                 */
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') {
                        return;
                    }
                    closeMobileToc();
                });

                /**
                 * TOC 链接点击跳转处理器
                 */
                const handleNavigation = function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    // 核心升级 4：使用 currentTarget 代替 this，完美防范触控下指针作用域丢失
                    const currentLink = e.currentTarget;

                    const targetId =
                        currentLink.getAttribute('data-target') ||
                        currentLink.getAttribute('href').substring(1);

                    const target = document.getElementById(targetId);

                    if (!target) return;

                    closeMobileToc();

                    links.forEach(function (l) {
                        l.classList.remove('is-active');
                    });

                    currentLink.classList.add('is-active');

                    const headerOffset = getHeaderOffset() + 15;

                    const offsetPosition =
                        target.getBoundingClientRect().top +
                        window.pageYOffset -
                        headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    history.pushState(null, null, '#' + targetId);
                };

                /**
                 * 防止 touch + click 双触发防御机制
                 */
                const supportsTouch =
                    'ontouchstart' in window ||
                    navigator.maxTouchPoints > 0;

                links.forEach(function (link) {
                    if (supportsTouch) {
                        // 移动端使用 passive: false 允许精准阻断默认滚屏
                        link.addEventListener(
                            'touchend',
                            handleNavigation,
                            { passive: false }
                        );
                    } else {
                        link.addEventListener(
                            'click',
                            handleNavigation
                        );
                    }
                });

                /**
                 * Scroll 滚动联动高亮
                 */
                function onMobileScroll() {
                    if (
                        !sections.length ||
                        window.getComputedStyle(tocMobile).display === 'none'
                    ) {
                        return;
                    }

                    const scrollPos =
                        window.pageYOffset +
                        getHeaderOffset() +
                        35;

                    let current = null;

                    for (let i = 0; i < sections.length; i++) {
                        const absTop =
                            sections[i].element.getBoundingClientRect().top +
                            window.pageYOffset;

                        if (absTop <= scrollPos) {
                            current = sections[i];
                        }
                    }

                    if (current) {
                        const active = tocMobile.querySelector(
                            '.crs-toc-mobile__link.is-active'
                        );

                        if (!active || active !== current.link) {
                            links.forEach(function (l) {
                                l.classList.remove('is-active');
                            });

                            current.link.classList.add('is-active');
                        }
                    }
                }

                /**
                 * 滚动时自动关闭 TOC 容器并防抖触发监听
                 */
                window.addEventListener('scroll', function () {
                    if (tocMobile.classList.contains('is-open')) {
                        closeMobileToc();
                    }

                    requestAnimationFrame(
                        onMobileScroll
                    );
                }, { passive: true });

                // 初始化首次执行高亮探测
                onMobileScroll();

            } // ← 闭合 if (trigger && links.length)
        } // ← 闭合 if (tocMobile)
        });
    })();