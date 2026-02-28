import DOMPurify from 'dompurify'

/**
 * Sanitize HTML content to prevent XSS attacks.
 * Allows safe HTML tags used in markdown rendering.
 */
export function sanitizeHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: [
      'p', 'br', 'strong', 'em', 'code', 'pre', 'a', 'ul', 'ol', 'li',
      'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'div', 'span', 'blockquote',
      'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'del', 'sup', 'sub',
      'details', 'summary', 'input',
    ],
    ALLOWED_ATTR: [
      'href', 'target', 'rel', 'class', 'src', 'alt', 'title', 'width', 'height',
      'type', 'checked', 'disabled',
    ],
    ALLOW_DATA_ATTR: false,
  })
}
