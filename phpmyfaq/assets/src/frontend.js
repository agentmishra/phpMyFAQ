/**
 * phpMyFAQ frontend code
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at https://mozilla.org/MPL/2.0/.
 *
 * @package   phpMyFAQ
 * @author    Thorsten Rinne
 * @copyright 2019-2023 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2019-12-20
 */

import 'cookieconsent';
import 'bootstrap';
import Masonry from 'masonry-layout';
import { handleReloadCaptcha } from './captcha';
import { calculateReadingTime } from './reading-time';

// Reload Captchas
const reloadButton = document.querySelector('#captcha-button');
if (reloadButton !== null) {
  handleReloadCaptcha(reloadButton);
}

// Calculate reading time
const faqBody = document.querySelector('.pmf-faq-body');
if (faqBody !== null) {
  calculateReadingTime();
}

// Masonry
window.onload = () => {
  const masonryElement = document.querySelector('.masonry-grid');
  if (masonryElement) {
    new Masonry(masonryElement, { columnWidth: 0 });
  }
};
