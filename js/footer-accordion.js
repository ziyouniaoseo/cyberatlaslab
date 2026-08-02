/**
 * Footer Accordion - 平板+手机端导航折叠交互
 * 
 * 断点：≤1024px 触发折叠（与 CSS 保持一致）
 * 桌面端（>1024px）自动展开，无需点击
 */

(function() {
    'use strict';

    // 断点必须与 CSS 中的 @media (max-width: 1024px) 保持一致
    const BREAKPOINT = 1024;

    document.addEventListener('DOMContentLoaded', function() {
        initFooterAccordion();
    });

    function initFooterAccordion() {
        const columns = document.querySelectorAll('.cal-footer__column');
        
        if (!columns.length) return;

        columns.forEach(function(column) {
            const toggle = column.querySelector('.cal-footer__nav-toggle');
            const list = column.querySelector('.cal-footer__nav-list');
            
            if (!toggle || !list) return;

            const header = column.querySelector('.cal-footer__column-header');
            
            // 点击标题区域展开/收起
            if (header) {
                header.addEventListener('click', function(e) {
                    if (window.innerWidth >= BREAKPOINT) return;
                    e.preventDefault();
                    toggleColumn(column, toggle);
                });
            }

            // 直接点击按钮
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth >= BREAKPOINT) return;
                e.preventDefault();
                e.stopPropagation();
                toggleColumn(column, toggle);
            });
        });

        // 窗口尺寸变化时重置
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= BREAKPOINT) {
                    columns.forEach(function(column) {
                        column.classList.remove('is-open');
                        const toggle = column.querySelector('.cal-footer__nav-toggle');
                        if (toggle) {
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            }, 150);
        });
    }

    function toggleColumn(column, toggle) {
        const isOpen = column.classList.contains('is-open');
        
        // 手风琴模式：关闭其他所有（可选，如需手风琴效果取消注释）
        // document.querySelectorAll('.cal-footer__column').forEach(function(c) {
        //     c.classList.remove('is-open');
        //     const t = c.querySelector('.cal-footer__nav-toggle');
        //     if (t) t.setAttribute('aria-expanded', 'false');
        // });
        
        // 切换当前
        column.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', !isOpen);
    }
})();