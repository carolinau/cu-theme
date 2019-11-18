<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Command;

// @codingStandardsIgnoreStart
use Drupal\Console\Annotations\DrupalCommand;
// @codingStandardsIgnoreEND
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\entity_share_client\Service\EntityShareClientCliService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class PullCommand.
 *
 * @package Drupal\entity_share_client
 *
 * @DrupalCommand (
 *   extension="entity_share_client",
 *   extensionType="module"
 * )
 */
class PullCommand extends Command {

  use CommandTrait;

  /**
   * The cli service doing all the work.
   *
   * @var \Drupal\entity_share_client\Service\EntityShareClientCliService
   */
  protected $cliService;

  /**
   * The io interface composed of a commands input and output.
   *
   * @var \Symfony\Component\Console\Style\StyleInterface
   */
  protected $io;

  /**
   * Constructor with cli service injection.
   *
   * @param \Drupal\entity_share_client\Service\EntityShareClientCliService $cliService
   *   The cli service to delegate all actions to.
   */
  public function __construct(EntityShareClientCliService $cliService) {
    parent::__construct();
    $this->cliService = $cliService;
  }

  /**
   * Set up the io interface.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output interface.
   */
  protected function setupIo(InputInterface $input, OutputInterface $output) {
    $this->io = new DrupalStyle($input, $output);
  }

  /**
   * Get the io interface.
   *
   * @return \Symfony\Component\Console\Style\StyleInterface
   *   The io interface.
   */
  protected function getIo() {
    return $this->io;
  }

  /**
   * The translation function akin to drupal's t().
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   The replacements.
   *
   * @return string
   *   The translated string.
   */
  public function t($string, array $args = []) {
    $c = 'commands.' . strtr($this->getName(), [':' => '.']) . '.messages.';
    $translations = [
      'Channel successfully pulled. Execution time @time ms.' => $c . 'success',
      'There is no remote website configured with the id: @remote_id.' => $c . 'no_remote',
      'There is no channel configured or accessible with the id: @channel_id.' => $c . 'no_channel',
      'Beginning to import content from URL: @url' => $c . 'beginning_url_import',
      '@number entities have been imported.' => $c . 'number_imported',
      'Looking for new content in channel @channel' => $c . 'update_channel_lookup',
      'Looking for updated content at URL: @url' => $c . 'update_page_lookup',
      'Channel successfully pulled. Number of updated entities: @count, execution time: @time ms' => $c . 'update_success'
    ];
    if (array_key_exists($string, $translations)) {
      $string = $translations[$string];
    }

    // Translate with consoles translations.
    return strtr($this->trans($string), $args);
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('entity_share_client:pull')
      ->setDescription($this->trans('commands.entity_share_client.pull.description'))
      ->addArgument('remote_id', InputArgument::REQUIRED, $this->trans('commands.entity_share_client.pull.arguments.remote_id'))
      ->addArgument('channel_id', InputArgument::REQUIRED, $this->trans('commands.entity_share_client.pull.arguments.channel_id'))
      ->addOption('update', 'u', InputOption::VALUE_OPTIONAL, $this->trans('commands.entity_share_client.pull.options.update'), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->setupIo($input, $output);
    try {
      // Make the magic happen.
      $update = $input->getOption('update') !== FALSE;

      if ($update) {
        $this->cliService->ioPullUpdates(
          $input->getArgument('remote_id'),
          $input->getArgument('channel_id'),
          $this->getIo(),
          [$this, 't']
        );
      }
      else {
        $this->cliService->ioPull(
          $input->getArgument('remote_id'),
          $input->getArgument('channel_id'),
          $this->getIo(),
          [$this, 't']
        );
      }
    }
    catch (\Exception $e) {
      $this->getIo()->error($e->getMessage());
    }
  }

}
