<?php

/**
 * The REST API
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at https://mozilla.org/MPL/2.0/.
 *
 * @deprecated will be migrated to api/index.php
 *
 * @package   phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2009-2023 phpMyFAQ Team
 * @license   https://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2009-09-03
 */

const IS_VALID_PHPMYFAQ = null;

use phpMyFAQ\Category;
use phpMyFAQ\Category\CategoryPermission;
use phpMyFAQ\Configuration;
use phpMyFAQ\Entity\CategoryEntity;
use phpMyFAQ\Entity\FaqEntity;
use phpMyFAQ\Faq;
use phpMyFAQ\Faq\FaqMetaData;
use phpMyFAQ\Filter;
use phpMyFAQ\Helper\QuestionHelper;
use phpMyFAQ\Helper\RegistrationHelper;
use phpMyFAQ\Language;
use phpMyFAQ\Question;
use phpMyFAQ\Services;
use phpMyFAQ\Strings;
use phpMyFAQ\Tags;
use phpMyFAQ\Translation;
use phpMyFAQ\User\CurrentUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

//
// Bootstrapping
//
require 'src/Bootstrap.php';

$faqConfig = Configuration::getConfigurationInstance();

//
// Create Request & Response
//
$response = new JsonResponse();
$request = Request::createFromGlobals();

$action = Filter::filterVar($request->get('action'), FILTER_SANITIZE_SPECIAL_CHARS);
$lang = Filter::filterVar($request->get('lang'), FILTER_SANITIZE_SPECIAL_CHARS, 'en');
$categoryId = Filter::filterVar($request->get('categoryId'), FILTER_VALIDATE_INT);
$recordId = Filter::filterVar($request->get('recordId'), FILTER_VALIDATE_INT);
$tagId = Filter::filterVar($request->get('tagId'), FILTER_VALIDATE_INT);

$faqUsername = Filter::filterVar($request->request->get('username'), FILTER_SANITIZE_SPECIAL_CHARS);
$faqPassword = Filter::filterVar(
    $request->request->get('password'),
    FILTER_SANITIZE_SPECIAL_CHARS,
    FILTER_FLAG_NO_ENCODE_QUOTES
);

//
// Get language (default: english)
//
$language = new Language($faqConfig);
$currentLanguage = $language->setLanguageByAcceptLanguage();

//
// Set language
//
if (Language::isASupportedLanguage($currentLanguage)) {
    require PMF_TRANSLATION_DIR . '/language_' . $currentLanguage . '.php';
} else {
    require PMF_TRANSLATION_DIR . '/language_en.php';
}
$faqConfig->setLanguage($language);

//
// Set translation class
//
try {
    Translation::create()
        ->setLanguagesDir(PMF_TRANSLATION_DIR)
        ->setDefaultLanguage('en')
        ->setCurrentLanguage($currentLanguage);
} catch (Exception $e) {
    $response->setStatusCode(Response::HTTP_BAD_REQUEST);
    $response->setData(['error' => $e->getMessage()]);
}

//
// Initializing static string wrapper
//
Strings::init($currentLanguage);

//
// Set empty result
//
$result = [];

//
// Check if user is already authenticated
//
$user = CurrentUser::getCurrentUser($faqConfig);
[ $currentUser, $currentGroups ] = CurrentUser::getCurrentUserGroupId($user);

//
// Handle actions
//
switch ($action) {
    //
    // v2.3
    //
    case 'category':
        $category = new Category($faqConfig, $currentGroups, true);
        $category->setUser($currentUser);
        $category->setGroups($currentGroups);
        $category->setLanguage($currentLanguage);

        $categoryPermission = new CategoryPermission($faqConfig);

        //
        // POST
        //
        if ($faqConfig->get('api.apiClientToken') !== $request->headers->get('x-pmf-token')) {
            $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
            $result = [
                'stored' => false,
                'error' => 'X_PMF_Token not valid.'
            ];
            break;
        }

        $postData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

        $languageCode = Filter::filterVar($postData['language'], FILTER_SANITIZE_SPECIAL_CHARS);
        $parentId = Filter::filterVar($postData['parent-id'], FILTER_VALIDATE_INT);

        if (isset($postData['parent-category-name'])) {
            $parentCategoryName = Filter::filterVar($postData['parent-category-name'], FILTER_SANITIZE_SPECIAL_CHARS);
        } else {
            $parentCategoryName = null;
        }

        $name = Filter::filterVar($postData['category-name'], FILTER_SANITIZE_SPECIAL_CHARS);
        $description = Filter::filterVar($postData['description'], FILTER_SANITIZE_SPECIAL_CHARS);

        if (isset($postData['user-id'])) {
            $userId = Filter::filterVar($postData['user-id'], FILTER_VALIDATE_INT);
        } else {
            $userId = 1;
        }

        if (isset($postData['group-id'])) {
            $groupId = Filter::filterVar($postData['group-id'], FILTER_VALIDATE_INT);
        } else {
            $groupId = -1;
        }

        $active = Filter::filterVar($postData['is-active'], FILTER_VALIDATE_BOOLEAN);
        $showOnHome = Filter::filterVar($postData['show-on-homepage'], FILTER_VALIDATE_BOOLEAN);

        // Check if the parent category name can be mapped
        if (!is_null($parentCategoryName)) {
            $parentCategoryIdFound = $category->getCategoryIdFromName($parentCategoryName);
            if ($parentCategoryIdFound === false) {
                $response->setStatusCode(Response::HTTP_CONFLICT);
                $result = [
                    'stored' => false,
                    'error' => 'The given parent category name was not found.'
                ];
                break;
            }

            $parentId = $parentCategoryIdFound;
        }

        $categoryData = new CategoryEntity();
        $categoryData
            ->setLang($languageCode)
            ->setParentId($parentId)
            ->setName($name)
            ->setDescription($description)
            ->setUserId($userId)
            ->setGroupId($groupId)
            ->setActive($active)
            ->setImage('')
            ->setShowHome($showOnHome);

        $categoryId = $category->create($categoryData);

        if ($categoryId) {
            $categoryPermission->add(CategoryPermission::USER, [$categoryId], [-1]);
            $categoryPermission->add(CategoryPermission::GROUP, [$categoryId], [-1]);

            $response->setStatusCode(Response::HTTP_OK);
            $result = [
                'stored' => true
            ];
        } else {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $result = [
                'stored' => false,
                'error' => 'Cannot add category'
            ];
        }
        $response->setData($result);
        break;

    case 'faqs':
        $filter = Filter::filterInput(INPUT_GET, 'filter', FILTER_SANITIZE_SPECIAL_CHARS);
        $faq = new Faq($faqConfig);
        $faq->setUser($currentUser);
        $faq->setGroups($currentGroups);

        // api/v2.2/faqs/:categoryId
        if (!is_null($categoryId)) {
            try {
                if ('all' === $filter) {
                    $result = $faq->getAllFaqsByCategoryId($categoryId, 'id', 'ASC', false);
                } else {
                    $result = $faq->getAllFaqsByCategoryId($categoryId);
                }
            } catch (Exception) {
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            }
        }

        // api/v2.2/faqs/tags/:tagId
        if (!is_null($tagId)) {
            $tags = new Tags($faqConfig);
            $recordIds = $tags->getFaqsByTagId($tagId);
            try {
                $result = $faq->getRecordsByIds($recordIds);
            } catch (Exception) {
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            }
        }

        // api/v2.2/faqs/popular
        if ('popular' === $filter) {
            $result = array_values($faq->getTopTenData());
        }

        // api/v2.2/faqs/latest
        if ('latest' === $filter) {
            $result = array_values($faq->getLatestData());
        }

        // api/v2.2/faqs/sticky
        if ('sticky' === $filter) {
            $result = array_values($faq->getStickyRecordsData());
        }

        // api/v2.2/faqs
        if (is_null($categoryId) && is_null($tagId) && is_null($filter)) {
            $faq->getAllRecords(FAQ_SORTING_TYPE_CATID_FAQID, ['lang' => $currentLanguage]);
            $result = $faq->faqRecords;
        }

        if ((is_countable($result) ? count($result) : 0) === 0) {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
        }
        $response->setData($result);
        break;


    case 'faq':
        //
        // GET
        //
        $filter = Filter::filterInput(INPUT_GET, 'filter', FILTER_SANITIZE_SPECIAL_CHARS);
        $faq = new Faq($faqConfig);
        $faq->setUser($currentUser);
        $faq->setGroups($currentGroups);

        if ($request->getMethod() === 'GET' && $recordId > 0) {
            $faq->getRecord($recordId);
            $result = $faq->faqRecord;

            if ((is_countable($result) ? count($result) : 0) === 0 || $result['solution_id'] === 42) {
                $result = new stdClass();
                $response->setStatusCode(Response::HTTP_NOT_FOUND);
            }

            if ('pdf' === $filter) {
                $service = new Services($faqConfig);
                $service->setFaqId($recordId);
                $service->setLanguage($currentLanguage);
                $service->setCategoryId($categoryId);

                $result = $service->getPdfApiLink();
            }
            $response->setData($result);
            break;
        }

        //
        // POST or PUT
        //
        if ($faqConfig->get('api.apiClientToken') !== $request->headers->get('x-pmf-token')) {
            $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
            $result = [
                'stored' => false,
                'error' => 'X_PMF_Token not valid.'
            ];
            $response->setData($result);
            break;
        }

        $category = new Category($faqConfig, $currentGroups, true);
        $category->setUser($currentUser);
        $category->setGroups($currentGroups);
        $category->setLanguage($currentLanguage);

        $postData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

        if (isset($postData['faq-id'])) {
            $faqId = Filter::filterVar($postData['faq-id'], FILTER_VALIDATE_INT);
        } else {
            $faqId = null;
        }
        $languageCode = Filter::filterVar($postData['language'], FILTER_SANITIZE_SPECIAL_CHARS);
        $categoryId = Filter::filterVar($postData['category-id'], FILTER_VALIDATE_INT);
        if (isset($postData['category-name'])) {
            $categoryName = Filter::filterVar($postData['category-name'], FILTER_SANITIZE_SPECIAL_CHARS);
        } else {
            $categoryName = null;
        }
        $question = Filter::filterVar($postData['question'], FILTER_SANITIZE_SPECIAL_CHARS);
        $answer = Filter::filterVar($postData['answer'], FILTER_SANITIZE_SPECIAL_CHARS);
        $keywords = Filter::filterVar($postData['keywords'], FILTER_SANITIZE_SPECIAL_CHARS);
        $author = Filter::filterVar($postData['author'], FILTER_SANITIZE_SPECIAL_CHARS);
        $email = Filter::filterVar($postData['email'], FILTER_SANITIZE_EMAIL);
        $isActive = Filter::filterVar($postData['is-active'], FILTER_VALIDATE_BOOLEAN);
        $isSticky = Filter::filterVar($postData['is-sticky'], FILTER_VALIDATE_BOOLEAN);

        // Check if category name can be mapped
        if (!is_null($categoryName)) {
            $categoryIdFound = $category->getCategoryIdFromName($categoryName);
            if ($categoryIdFound === false) {
                $response->setStatusCode(Response::HTTP_CONFLICT);
                $result = [
                    'stored' => false,
                    'error' => 'The given category name was not found.'
                ];
                $response->setData($result);
                break;
            }

            $categoryId = $categoryIdFound;
        }

        if ($faq->hasTitleAHash($question) {
            $response->setStatusCode(400);
            $result = [
                'stored' => false,
                'error' => 'It is not allowed, that the question title contains a hash.'
            ];
            $response->setData($result);
            break;
        }

        $categories = [ $categoryId ];
        $isActive = !is_null($isActive);
        $isSticky = !is_null($isSticky);

        $faqData = new FaqEntity();
        $faqData
            ->setLanguage($languageCode)
            ->setQuestion($question)
            ->setAnswer($answer)
            ->setKeywords($keywords)
            ->setAuthor($author)
            ->setEmail($email)
            ->setActive($isActive)
            ->setSticky($isSticky)
            ->setComment(false)
            ->setNotes('');

        if (is_null($faqId)) {
            $faqId = $faq->create($faqData);
        } else {
            $faqData->setId($faqId);
            $faqData->setRevisionId(0);
            $faq->update($faqData);
        }

        if ($request->getMethod() !== 'PUT') {
            $faqMetaData = new FaqMetaData($faqConfig);
            $faqMetaData->setFaqId($faqId)->setFaqLanguage($languageCode)->setCategories($categories)->save();
        }

        $result = [
            'stored' => true
        ];
        $response->setData($result);
        break;

    case 'register':
        if ($faqConfig->get('api.apiClientToken') !== $request->headers->get('x-pmf-token')) {
            $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
            $result = [
                'registered' => false,
                'error' => 'X_PMF_Token not valid.'
            ];
            $response->setData($result);
            break;
        }

        $registration = new RegistrationHelper($faqConfig);

        $userName = Filter::filterInput(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        $fullName = Filter::filterInput(INPUT_POST, 'fullname', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = Filter::filterInput(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $isVisible = Filter::filterInput(INPUT_POST, 'is-visible', FILTER_SANITIZE_SPECIAL_CHARS);
        $isVisible = $isVisible === 'true';

        if (!$registration->isDomainAllowed($email)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $result = [
                'registered' => false,
                'error' => 'The domain is not whitelisted.'
            ];
            $response->setData($result);
            break;
        }

        if (!is_null($userName) && !is_null($fullName) && !is_null($email)) {
            $result = $registration->createUser($userName, $fullName, $email, $isVisible);
            $response->setStatusCode(Response::HTTP_OK);
        } else {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $result = [
                'registered' => false,
                'error' => Translation::get('err_sendMail')
            ];
        }
        $response->setData($result);
        break;

    case 'question':
        if ($faqConfig->get('api.apiClientToken') !== $request->headers->get('x-pmf-token')) {
            $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
            $response->setData([
                'stored' => false,
                'error' => 'X_PMF_Token not valid.'
            ]);
            break;
        }

        $postData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $languageCode = Filter::filterVar($postData['language'], FILTER_SANITIZE_SPECIAL_CHARS);
        $categoryId = Filter::filterVar($postData['category-id'], FILTER_VALIDATE_INT);
        $question = Filter::filterVar($postData['question'], FILTER_SANITIZE_SPECIAL_CHARS);
        $author = Filter::filterVar($postData['author'], FILTER_SANITIZE_SPECIAL_CHARS);
        $email = Filter::filterVar($postData['email'], FILTER_SANITIZE_SPECIAL_CHARS);

        if ($faqConfig->get('records.enableVisibilityQuestions')) {
            $visibility = 'Y';
        } else {
            $visibility = 'N';
        }

        $questionData = [
            'username' => $author,
            'email' => $email,
            'category_id' => $categoryId,
            'question' => $question,
            'is_visible' => $visibility
        ];

        $questionObject = new Question($faqConfig);
        $questionObject->addQuestion($questionData);

        $categoryObject = new Category($faqConfig);
        $categoryObject->getCategoryData($categoryId);
        $categories = $categoryObject->getAllCategories();

        $questionHelper = new QuestionHelper($faqConfig, $categoryObject);
        try {
            $questionHelper->sendSuccessMail($questionData, $categories);
        } catch (TransportExceptionInterface | Exception $e) {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['error' => $e->getMessage() ]);
        }

        $response->setData(['stored' => true]);
        break;
}

//
// Check if the FAQ should be secured
//
if (!$user->isLoggedIn() && $faqConfig->get('security.enableLoginOnly')) {
    $response->setStatusCode(Response::HTTP_FORBIDDEN);
    $response->setData([ 'error' => 'You are not allowed to view this content.' ]);
}

$response->send();
