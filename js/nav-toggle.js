/**
 * CyberAtlasLab 导航交互脚本
 * 
 * 功能：
 * 1. 移动端菜单状态切换 + 滚动锁定（防止背景穿透）
 * 2. 移动端 Apple 风格二级菜单（全屏显示 + 返回按钮）
 * 3. 移动端倒三角图标修复（替换 SVG 为 CSS 三角）
 * 4. 搜索按钮 title 移除
 * 5. 桌面端 Mega Menu 面板宽度与定位
 * 
 * 版本：3.2.0
 * 更新：
 * - 桌面端 Mega Menu 按列数区分宽度
 * - 窄面板以菜单项为中心定位
 * - 修复 Edge 浏览器 F12 切换视口时的样式残留问题
 */

(function () {
    'use strict';

    // =========================================================================
    // 全局变量
    // =========================================================================
    var scrollPosition = 0;
    var isMenuOpen = false;
    var currentOpenSubmenu = null;  // 当前打开的二级菜单

    // =========================================================================
    // 1. 滚动锁定（防止背景穿透）
    // =========================================================================

    function lockBodyScroll() {
        if (isMenuOpen) return;
        isMenuOpen = true;

        scrollPosition = window.pageYOffset || document.documentElement.scrollTop;

        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = '-' + scrollPosition + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
        document.documentElement.style.overflow = 'hidden';

        document.body.classList.add('cal-menu-open');
    }

    function unlockBodyScroll() {
        if (!isMenuOpen) return;
        isMenuOpen = false;

        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        document.documentElement.style.overflow = '';

        document.body.classList.remove('cal-menu-open');

        window.scrollTo(0, scrollPosition);
    }

    function watchMenuState() {
        var menuContainer = document.querySelector('.elementskit-menu-container');
        if (!menuContainer) {
            setTimeout(watchMenuState, 500);
            return;
        }

        if (menuContainer.dataset.calWatching) return;
        menuContainer.dataset.calWatching = 'true';

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.attributeName === 'class') {
                    var isActive = menuContainer.classList.contains('active');
                    if (isActive && window.innerWidth <= 1024) {
                        lockBodyScroll();
                    } else {
                        unlockBodyScroll();
                        closeSubmenuPanel();
                    }
                }
            });
        });

        observer.observe(menuContainer, { attributes: true });

        if (menuContainer.classList.contains('active') && window.innerWidth <= 1024) {
            lockBodyScroll();
        }
    }

    // =========================================================================
    // 2. Apple 风格移动端菜单
    // =========================================================================

    /**
     * 创建全屏二级菜单面板
     */
    function createSubmenuPanel() {
        var existing = document.querySelector('#cal-submenu-panel');
        if (existing) return existing;

        var panel = document.createElement('div');
        panel.id = 'cal-submenu-panel';
        panel.className = 'cal-submenu-panel';

        // 头部：返回按钮 + 标题 + 关闭按钮
        var header = document.createElement('div');
        header.className = 'cal-submenu-header';

        // 返回按钮
        var backBtn = document.createElement('button');
        backBtn.className = 'cal-submenu-back';
        backBtn.setAttribute('aria-label', 'Back');
        backBtn.innerHTML = '<span class="cal-back-arrow"></span>';
        backBtn.addEventListener('click', closeSubmenuPanel);

        // 标题
        var title = document.createElement('span');
        title.className = 'cal-submenu-title';
        title.id = 'cal-submenu-title';

        // 关闭按钮
        var closeBtn = document.createElement('button');
        closeBtn.className = 'cal-submenu-close';
        closeBtn.setAttribute('aria-label', 'Close menu');
        closeBtn.addEventListener('click', function () {
            closeSubmenuPanel();
            var mainCloseBtn = document.querySelector('.elementskit-menu-close');
            if (mainCloseBtn) mainCloseBtn.click();
        });

        header.appendChild(backBtn);
        header.appendChild(title);
        header.appendChild(closeBtn);

        // 内容区域
        var content = document.createElement('div');
        content.className = 'cal-submenu-content';
        content.id = 'cal-submenu-content';

        panel.appendChild(header);
        panel.appendChild(content);

        document.body.appendChild(panel);

        return panel;
    }

    /**
     * 打开全屏二级菜单
     */
    function openSubmenuPanel(menuItem, menuTitle) {
        var panel = createSubmenuPanel();
        var content = document.getElementById('cal-submenu-content');
        var title = document.getElementById('cal-submenu-title');

        title.textContent = menuTitle;

        var submenuPanel = menuItem.querySelector('.elementskit-megamenu-panel, .elementskit-submenu-panel');
        if (!submenuPanel) return;

        content.innerHTML = '';
        var clone = submenuPanel.cloneNode(true);
        clone.style.display = 'block';
        clone.style.position = 'relative';
        clone.style.width = '100%';
        clone.style.maxWidth = '100%';
        clone.style.height = 'auto';
        clone.style.maxHeight = 'none';
        clone.style.transform = 'none';
        clone.style.boxShadow = 'none';
        clone.style.border = 'none';
        clone.style.borderRadius = '0';
        clone.style.margin = '0';
        clone.style.boxSizing = 'border-box';
        clone.style.background = '#ffffff';
        content.appendChild(clone);

        panel.classList.add('cal-submenu-panel-active');
        currentOpenSubmenu = menuItem;
        content.scrollTop = 0;
    }

    /**
     * 关闭全屏二级菜单
     */
    function closeSubmenuPanel() {
        var panel = document.querySelector('#cal-submenu-panel');
        if (panel) {
            panel.classList.remove('cal-submenu-panel-active');
        }
        currentOpenSubmenu = null;
    }

    /**
     * 初始化移动端菜单交互
     */
    function initNavToggle() {
        if (window.innerWidth > 1024) return;

        var navbar = document.querySelector('.main-nav .elementskit-navbar-nav');
        if (!navbar) return;

        var allMenuItems = navbar.querySelectorAll(':scope > li');

        allMenuItems.forEach(function (item) {
            var link = item.querySelector(':scope > a');
            if (!link) return;

            if (link.dataset.calBound) return;
            link.dataset.calBound = 'true';

            link.addEventListener('click', function (e) {
                var hasMegamenu = item.classList.contains('elementskit-megamenu-has') ||
                    item.classList.contains('elementskit-dropdown-has');

                if (hasMegamenu) {
                    e.preventDefault();
                    var menuTitle = link.textContent.trim();
                    openSubmenuPanel(item, menuTitle);
                }

                allMenuItems.forEach(function (li) {
                    li.classList.remove('cal-menu-active');
                });
                item.classList.add('cal-menu-active');
                navbar.classList.add('cal-has-open-menu');
            });
        });

        var closeBtn = document.querySelector('.elementskit-menu-close');
        if (closeBtn && !closeBtn.dataset.calBound) {
            closeBtn.dataset.calBound = 'true';
            closeBtn.addEventListener('click', function () {
                navbar.classList.remove('cal-has-open-menu');
                allMenuItems.forEach(function (li) {
                    li.classList.remove('cal-menu-active');
                });
                closeSubmenuPanel();
            });
        }
    }

    // =========================================================================
    // 3. 移动端倒三角修复
    // =========================================================================

    var originalIndicators = new Map();

    /**
     * 恢复桌面端原始倒三角 SVG
     */
    function restoreDesktopIndicators() {
        if (window.innerWidth <= 1024) return;

        var customIndicators = document.querySelectorAll('.cal-submenu-indicator.cal-indicator-fixed');

        customIndicators.forEach(function (span) {
            var parent = span.parentNode;
            if (!parent || parent.tagName !== 'A') return;

            var originalSvg = originalIndicators.get(parent);
            if (originalSvg) {
                var clonedSvg = originalSvg.cloneNode(true);
                parent.replaceChild(clonedSvg, span);
            }
        });

        var panel = document.querySelector('#cal-submenu-panel');
        if (panel) {
            panel.classList.remove('cal-submenu-panel-active');
        }
    }

    function fixSubmenuIndicator() {
        if (window.innerWidth > 1024) return;

        var indicators = document.querySelectorAll('.elementskit-submenu-indicator:not(.cal-indicator-fixed)');

        indicators.forEach(function (svg) {
            var parent = svg.parentNode;
            if (!parent || parent.tagName !== 'A') return;

            var menuItem = parent.closest('.elementskit-dropdown-has, .elementskit-megamenu-has');
            if (!menuItem) return;

            if (!originalIndicators.has(parent)) {
                originalIndicators.set(parent, svg.cloneNode(true));
            }

            var newIndicator = document.createElement('span');
            newIndicator.className = 'cal-submenu-indicator cal-indicator-fixed';
            newIndicator.setAttribute('role', 'button');
            newIndicator.setAttribute('tabindex', '0');
            newIndicator.setAttribute('aria-label', 'Open submenu');

            newIndicator.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (window.innerWidth > 1024) return;

                var link = menuItem.querySelector(':scope > a');
                var menuTitle = link ? link.textContent.trim() : '';

                openSubmenuPanel(menuItem, menuTitle);

                var navbar = document.querySelector('.main-nav .elementskit-navbar-nav');
                if (navbar) {
                    var allMenuItems = navbar.querySelectorAll(':scope > li');
                    allMenuItems.forEach(function (li) {
                        li.classList.remove('cal-menu-active');
                    });
                    menuItem.classList.add('cal-menu-active');
                    navbar.classList.add('cal-has-open-menu');
                }
            });

            newIndicator.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    newIndicator.click();
                }
            });

            parent.replaceChild(newIndicator, svg);
        });
    }

    // =========================================================================
    // 4. 搜索按钮 title 移除
    // =========================================================================
    function removeSearchTitle() {
        var searchToggle = document.querySelector('.bdt-search-toggle');
        if (searchToggle) {
            searchToggle.removeAttribute('title');
            searchToggle.setAttribute('aria-label', '搜索');
        }
    }

    // =========================================================================
    // 5. 桌面端 Mega Menu 面板宽度与定位
    // 
    // 策略：
    // - 4列菜单（默认）：视口居中，1200px
    // - 1-3列菜单：以菜单项为中心，自动边界检测
    // 
    // 配置说明：
    // - 新增菜单时，在 megaMenuWidths 中添加 'menu-item-ID': 宽度
    // - 1列: 320px, 2列: 600px, 3列: 900px, 4列: 1200px（默认）
    // =========================================================================

    var megaMenuWidths = {
        'menu-item-2629': 900,   // Parental Control - 3列
        'menu-item-2630': 900,   // Guides - 3列
        'menu-item-2631': 600    // Toolbox - 2列
        // 其他菜单使用默认 1200px
    };

    function fixMegaMenuPosition() {
        if (window.innerWidth <= 1024) return;

        document.querySelectorAll('.elementskit-megamenu-has').forEach(function (li) {
            if (li.dataset.calMegamenuBound) return;
            li.dataset.calMegamenuBound = 'true';

            li.addEventListener('mouseenter', function () {
                var panel = li.querySelector(':scope > .elementskit-megamenu-panel');
                if (!panel) return;

                var width = megaMenuWidths[li.id] || 1200;

                panel.style.cssText = 'width:' + width + 'px !important; position:fixed !important; top:70px !important; margin:0 !important; max-width:calc(100vw - 40px) !important;';

                if (width < 1200) {
                    var rect = li.getBoundingClientRect();
                    var menuCenter = rect.left + rect.width / 2;
                    var panelLeft = menuCenter - width / 2;
                    var viewportWidth = window.innerWidth;

                    if (panelLeft + width > viewportWidth - 20) {
                        panelLeft = viewportWidth - width - 20;
                    }
                    if (panelLeft < 20) {
                        panelLeft = 20;
                    }

                    panel.style.cssText += 'left:' + panelLeft + 'px !important; right:auto !important; transform:none !important;';
                } else {
                    panel.style.cssText += 'left:50% !important; right:auto !important; transform:translateX(-50%) !important;';
                }
            });
        });
    }

    // =========================================================================
    // 6. 初始化
    // =========================================================================
    function init() {
        initNavToggle();
        fixSubmenuIndicator();
        removeSearchTitle();
        fixMegaMenuPosition();
        watchMenuState();

        if (window.innerWidth <= 1024) {
            createSubmenuPanel();
            watchPanelStyles();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // =========================================================================
    // 7. 窗口大小变化处理
    // =========================================================================
    var resizeTimer;
    var isMobileMode = window.innerWidth <= 1024;

    /**
     * 监听面板样式变化，在移动端阻止桌面端样式残留
     * 修复 Edge 浏览器 F12 切换视口时的问题
     */
    function watchPanelStyles() {
        document.querySelectorAll('.elementskit-megamenu-panel').forEach(function (panel) {
            if (panel.dataset.calStyleWatched) return;
            panel.dataset.calStyleWatched = 'true';

            var observer = new MutationObserver(function (mutations) {
                if (window.innerWidth <= 1024) {
                    mutations.forEach(function (mutation) {
                        if (mutation.attributeName === 'style') {
                            var style = panel.getAttribute('style') || '';
                            if (style.includes('fixed') || style.includes('translateX')) {
                                panel.removeAttribute('style');
                            }
                        }
                    });
                }
            });

            observer.observe(panel, { attributes: true, attributeFilter: ['style'] });
        });
    }

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            var wasMobile = isMobileMode;
            isMobileMode = window.innerWidth <= 1024;

            if (isMobileMode) {
                // 移动端：彻底清除桌面端的内联样式
                document.querySelectorAll('.elementskit-megamenu-panel').forEach(function (panel) {
                    panel.removeAttribute('style');
                });
                document.querySelectorAll('.elementskit-megamenu-has').forEach(function (li) {
                    li.dataset.calMegamenuBound = '';
                    li.removeAttribute('style');
                });

                watchPanelStyles();
                fixSubmenuIndicator();
            } else {
                unlockBodyScroll();
                closeSubmenuPanel();
                restoreDesktopIndicators();
            }
            initNavToggle();
            fixMegaMenuPosition();
        }, 250);
    });
})();