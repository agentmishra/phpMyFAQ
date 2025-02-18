<?php

/**
 * The Bookmark Controller
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at https://mozilla.org/MPL/2.0/.
 *
 * @package   phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2023 phpMyFAQ Team
 * @license   https://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2023-09-17
 */

namespace phpMyFAQ\Controller;

use phpMyFAQ\Bookmark;
use phpMyFAQ\Configuration;
use phpMyFAQ\Filter;
use phpMyFAQ\User\CurrentUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BookmarkController
{
    #[Route('api/bookmark')]
    public function delete(Request $request): JsonResponse
    {
        $response = new JsonResponse();
        $faqConfig = Configuration::getConfigurationInstance();

        $id = Filter::filterVar($request->get('bookmarkId'), FILTER_VALIDATE_INT);

        $currentUser = CurrentUser::getCurrentUser($faqConfig);

        $bookmark = new Bookmark($faqConfig, $currentUser);

        $response->setData(['success' => $bookmark->remove($id)]);

        return $response;
    }
}
