<?php

class DisqusExport extends Plugin
{
    /**
     * Executes when the admin plugins page wants to know if plugins have configuration links to display.
     *
     * @param  array  $actions   An array of existing actions for the specified plugin id.
     * @param  string $plugin_id The string id of a plugin, generated by the system
     * @return array The array of actions to attach to the specified $plugin_id
     */
    public function filter_plugin_config( $actions, $plugin_id )
    {
        if ( $plugin_id == $this->plugin_id() ) {
            $actions[] = _t( 'Configure' );
            $actions[] = _t( 'Export Comments' );
        }

        return $actions;
    }

    /**
    * Respond to the user selecting an action on the plugin page
    *
    * @param string $plugin_id The string id of the acted-upon plugin
    * @param string $action    The action string supplied via the filter_plugin_config hook
    */
    public function action_plugin_ui( $plugin_id, $action )
    {
        if ( $plugin_id == $this->plugin_id() ) {
            switch( $action ) {
                case _t( 'Configure' ):
                    $form = new FormUI(strtolower(get_class($this)));
                    $form->append('text', 'user_api_key', 'disqus__user_api_key', _t('User Key'));
                    $form->append('text', 'forum_api_key', 'disqus__forum_api_key', _t('Forum Key'));
                    $form->append('submit', 'save', 'Save');
                    $form->out();
                    break;
                case _t( 'Export Comments' ):
                    $this->export_comments();
                    break;
            }
        }
    }

    private function export_comments()
    {
        require_once 'vendor/disqus/disqus/disqus.php';

        $user_api_key  = Options::get('disqus__user_api_key');
        $forum_api_key = Options::get('disqus__forum_api_key');

        $disqus = new DisqusAPI($user_api_key, $forum_api_key);

        foreach (Posts::get(array('content_type' => 'entry', 'nolimit' => TRUE)) as $post)
        {
            // retrieve or create the current thread
            $thread = $disqus->thread_by_identifier($post->id, $post->title)->thread;

            foreach ($post->comments as $comment)
            {
            	// @todo filter trackbacks/pingbacks?

                // format params
                $params = array(
                    'created_at' => $comment->date->format('Y-m-d\TH:i'),
                    'ip_address' => $comment->ip,
                    'author_url' => $comment->url,
                );

				switch ($comment->status) {
					case Comment::STATUS_UNAPPROVED:
						$params['state'] = 'unapproved';
						break;
					case Comment::STATUS_APPROVED:
						$params['state'] = 'approved';
						break;
					case Comment::STATUS_SPAM:
						$params['state'] = 'spam';
						break;
					case Comment::STATUS_DELETED:
						$params['state'] = 'killed';
				}

                // create post
                $comment = $disqus->create_post($thread->id, $comment->content, $comment->name, $comment->email, $params);
            }
        }
    }
}

?>
