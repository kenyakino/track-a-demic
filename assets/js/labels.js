/**
 * Labels shared by the student and adviser pages.
 */

import { html } from './ui.js';

export const TOPIC_STATUS = {
  Proposed: { cls: 'proposed', help: "Waiting for your adviser's review." },
  Approved: { cls: 'approved', help: 'Approved by your adviser. Start working on your milestones.' },
  'In Progress': { cls: 'progress', help: 'Your group is working on this topic.' },
  Defended: { cls: 'defended', help: 'This topic has been defended.' },
  'For Revision': { cls: 'revision', help: 'Your adviser asked for changes. Edit the topic and resubmit it.' },
  Rejected: { cls: 'rejected', help: 'Your adviser did not accept this topic.' },
};

/** A status pill. The words are always shown, color is only extra. */
export const badge = (status) =>
  html`<span class="badge badge-${TOPIC_STATUS[status]?.cls ?? 'proposed'}">${status}</span>`;
