/**
 * Chart helpers. Chart.js is loaded from assets/vendor/chart.umd.js (saved in
 * the project, no outside link) and is available as window.Chart.
 * Each chart has a text summary for screen readers, and charts redraw when
 * the light/dark theme changes.
 */

const palette = () => {
  const dark = document.documentElement.dataset.theme === 'dark';
  const css = getComputedStyle(document.documentElement);
  const read = (name, fallback) => css.getPropertyValue(name).trim() || fallback;

  return {
    ink: read('--ink', '#000'),
    muted: read('--muted', '#3d4541'),
    line: read('--line', '#bfc5c1'),
    surface: read('--surface', '#f4f5f4'),
    green: dark ? '#5fbf8a' : '#2f6b4a',
    blue: dark ? '#6fb1e0' : '#2a6496',
    amber: dark ? '#e3b94e' : '#9a6a00',
    red: dark ? '#f08a80' : '#a4231b',
    grey: dark ? '#8d9792' : '#7b8680',
    soft: dark ? '#2a302d' : '#cbdcd1',
  };
};

const live = new Map();   // canvas id -> redraw function

/** Draws (or redraws) a chart in the canvas with the given id. */
export const drawChart = (canvasId, build) => {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !window.Chart) return;

  const draw = () => {
    window.Chart.getChart(canvas)?.destroy();
    const colors = palette();
    window.Chart.defaults.color = colors.ink;
    window.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    window.Chart.defaults.borderColor = colors.line;
    new window.Chart(canvas, build(colors));
  };

  live.set(canvasId, draw);
  draw();
};

new MutationObserver(() => live.forEach((draw, id) => (document.getElementById(id) ? draw() : live.delete(id))))
  .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

/** Topic status names in the order they are drawn, with their colors. */
export const topicStatusColors = (c) => ({
  'No topic': c.soft,
  Proposed: c.grey,
  'For Revision': c.amber,
  Approved: c.green,
  'In Progress': c.blue,
  Defended: c.ink,
});

export const milestoneStatusColors = (c) => ({
  'Not Started': c.soft,
  'In Progress': c.blue,
  Submitted: c.amber,
  'Needs Rework': c.red,
  Completed: c.green,
});
