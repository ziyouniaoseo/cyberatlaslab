/**
 * =============================================================================
 * CRS Mobile TOC Smart Fixed v2.1 (Pure Smooth Motion Pack)
 * 仅润滑浮现与 Sticky 阴影细节，100% 保持原有业务逻辑
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const MOBILE_BREAKPOINT = 1024;
    const TOC_GAP = 12;
    const CONTENT_END_BUFFER = 60;

    if (!window.matchMedia(`(max-width:${MOBILE_BREAKPOINT}px)`).matches) {
        return;
    }

    const toc = document.querySelector('.crs-block--toc-mobile');
    const content = document.querySelector('.crs-block--content');

    if (!toc || !content || window.__crsTocFixed) {
        return;
    }

    window.__crsTocFixed = true;

    let headerOffset = 88;
    let ticking = false;

    function getHeaderOffset() {
        const header =
            document.querySelector('.site-header') ||
            document.querySelector('header') ||
            document.querySelector('.elementor-sticky');

        if (!header) {
            return 88;
        }
        return Math.round(header.getBoundingClientRect().height) + TOC_GAP;
    }

    function refreshMeasurements() {
        headerOffset = getHeaderOffset();
        toc.style.top = `${headerOffset}px`;
    }

    refreshMeasurements();
    document.body.appendChild(toc);

    /* 高定贝塞尔曲线驱动位移和透明度，底色过渡瞬间高级 */
    toc.style.cssText += `
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        z-index: 100 !important;
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        transform: translateY(-100%);
        opacity: 0;
        pointer-events: none;
        box-shadow: 0 2px 10px rgba(148, 163, 184, 0.05) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    `;

    const tocInner = toc.querySelector('.crs-toc-mobile');
    if (tocInner) {
        tocInner.style.maxWidth = '100%';
        tocInner.style.margin = '0 auto';
    }

    function updateTocState() {
        const scrollY = window.scrollY;
        const rect = content.getBoundingClientRect();
        const contentTop = rect.top + window.scrollY;
        const contentBottom = rect.bottom + window.scrollY;

        const enteredContent = scrollY + headerOffset >= contentTop;
        const beforeContentEnd = scrollY + headerOffset < contentBottom - CONTENT_END_BUFFER;

        if (enteredContent && beforeContentEnd) {
            toc.style.transform = 'translateY(0)';
            toc.style.opacity = '1';
            toc.style.pointerEvents = 'auto';
        } else {
            toc.style.transform = 'translateY(-100%)';
            toc.style.opacity = '0';
            toc.style.pointerEvents = 'none';
        }

        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            requestAnimationFrame(updateTocState);
            ticking = true;
        }
    }, { passive: true });

    window.addEventListener('resize', function () {
        refreshMeasurements();
        requestAnimationFrame(updateTocState);
    });

    window.addEventListener('load', function () {
        refreshMeasurements();
        updateTocState();
    });

    setTimeout(function () {
        refreshMeasurements();
        updateTocState();
    }, 500);

    const contentImages = content.querySelectorAll('img');
    contentImages.forEach(function (img) {
        if (!img.complete) {
            img.addEventListener('load', function () {
                refreshMeasurements();
                updateTocState();
            });
        }
    });
});