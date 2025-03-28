<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\LanguageInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Url;
use Drupal\comment\Entity\Comment;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests the 'new' indicator posted on comments.
 *
 * @group comment
 */
class CommentNewIndicatorTest extends CommentTestBase {

  /**
   * Use the main node listing to test rendering on teasers.
   *
   * @var array
   *
   * @todo Remove this dependency.
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Get node "x new comments" metadata from the server for the current user.
   *
   * @param array $node_ids
   *   An array of node IDs.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function renderNewCommentsNodeLinks(array $node_ids): ResponseInterface {
    $client = $this->getHttpClient();
    $url = Url::fromRoute('comment.new_comments_node_links');

    return $client->request('POST', $this->buildUrl($url), [
      'cookies' => $this->getSessionCookies(),
      'http_errors' => FALSE,
      'form_params' => [
        'node_ids' => $node_ids,
        'field_name' => 'comment',
      ],
    ]);
  }

  /**
   * Tests new comment marker.
   */
  public function testCommentNewCommentsIndicator(): void {
    // Test if the right links are displayed when no comment is present for the
    // node.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node');
    $this->assertSession()->linkNotExists('0 comments');
    $this->assertSession()->linkExists('Read more');
    // Verify the data-history-node-last-comment-timestamp attribute, which is
    // used by the drupal.node-new-comments-link library to determine whether
    // a "x new comments" link might be necessary or not. We do this in
    // JavaScript to prevent breaking the render cache.
    $this->assertSession()->elementNotExists('xpath', '//*[@data-history-node-last-comment-timestamp]');

    // Create a new comment. This helper function may be run with different
    // comment settings so use $comment->save() to avoid complex setup.
    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = Comment::create([
      'cid' => NULL,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'field_name' => 'comment',
      'pid' => 0,
      'uid' => $this->loggedInUser->id(),
      'status' => CommentInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'hostname' => '127.0.0.1',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'comment_body' => [LanguageInterface::LANGCODE_NOT_SPECIFIED => [$this->randomMachineName()]],
    ]);
    $comment->save();
    $this->drupalLogout();

    // Log in with 'web user' and check comment links.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node');
    // Verify the data-history-node-last-comment-timestamp attribute. Given its
    // value, the drupal.node-new-comments-link library would determine that the
    // node received a comment after the user last viewed it, and hence it would
    // perform an HTTP request to render the "new comments" node link.
    $this->assertSession()->elementsCount('xpath', '//*[@data-history-node-last-comment-timestamp="' . $comment->getChangedTime() . '"]', 1);
    $this->assertSession()->elementsCount('xpath', '//*[@data-history-node-field-name="comment"]', 1);
    // The data will be pre-seeded on this particular page in drupalSettings, to
    // avoid the need for the client to make a separate request to the server.
    $settings = $this->getDrupalSettings();
    $this->assertEquals(['lastReadTimestamps' => [1 => 0]], $settings['history']);
    $this->assertEquals([
      'newCommentsLinks' => [
        'node' => [
          'comment' => [
            1 => [
              'new_comment_count' => 1,
              'first_new_comment_link' => Url::fromRoute('entity.node.canonical', ['node' => 1])->setOptions([
                'fragment' => 'new',
              ])->toString(),
            ],
          ],
        ],
      ],
    ], $settings['comment']);
    // Pretend the data was not present in drupalSettings, i.e. test the
    // separate request to the server.
    $response = $this->renderNewCommentsNodeLinks([$this->node->id()]);
    $this->assertSame(200, $response->getStatusCode());
    $json = Json::decode($response->getBody());
    $expected = [
      $this->node->id() => [
        'new_comment_count' => 1,
        'first_new_comment_link' => $this->node->toUrl('canonical', ['fragment' => 'new'])->toString(),
      ],
    ];
    $this->assertSame($expected, $json);

    // Failing to specify node IDs for the endpoint should return a 404.
    $response = $this->renderNewCommentsNodeLinks([]);
    $this->assertSame(404, $response->getStatusCode());

    // Accessing the endpoint as the anonymous user should return a 403.
    $this->drupalLogout();
    $response = $this->renderNewCommentsNodeLinks([$this->node->id()]);
    $this->assertSame(403, $response->getStatusCode());
    $response = $this->renderNewCommentsNodeLinks([]);
    $this->assertSame(403, $response->getStatusCode());
  }

}
