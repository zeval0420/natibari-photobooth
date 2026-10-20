/**
 * assets/js/sessionClient.js
 * ------------------------------------------------------------------
 * Every call from the kiosk frontend to the PHP backend goes through
 * here, so the rest of the app never builds a URL or talks raw fetch.
 * ------------------------------------------------------------------
 */
import { apiFetch, apiPost } from './utils.js';

export const SessionClient = {
  getPublicConfig() {
    return apiFetch('api/public_config.php');
  },

  getTemplates() {
    return apiFetch('api/templates.php');
  },

  createSession(templateId) {
    return apiPost('api/session_create.php', { template: templateId });
  },

  savePhoto(sessionId, index, dataUrl) {
    return apiPost('api/photo_save.php', { session_id: sessionId, index, image: dataUrl });
  },

  generateStrip(sessionId) {
    return apiPost('api/strip_generate.php', { session_id: sessionId });
  },

  acceptStrip(sessionId) {
    return apiPost('api/strip_accept.php', { session_id: sessionId });
  },
};
