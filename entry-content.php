<!-- entry-content.php -->
<div class="entry content">
    <?php if (has_post_thumbnail()): ?>
        <a class="thumbnail-link" href="<?php the_post_thumbnail_url('full'); ?>" title="<?php $attachment_id = get_post_thumbnail_id($post->ID); the_title_attribute(array('post' => get_post($attachment_id))); ?>">
            <?php the_post_thumbnail('full', array('itemprop' => 'image')); ?>
        </a>
    <?php endif; ?>
    <?php the_content(); ?>
    <?php
        // 1. Retrieve the tracklist data from the current post
        $tracklist = get_post_meta(get_the_ID(), 'tracklist', true);
        // 2. Check if we actually have data before printing anything
        if (!empty($tracklist) && is_array($tracklist)):
    ?>
            <h3>Tracklist/Timeline:</h3>
            <ul class="tracklist-display">
                <?php foreach ($tracklist as $item) : 
                    // Get the type (track or spacer) to use as a CSS class
                    $type = isset($item['type']) ? esc_attr($item['type']) : 'track';
                    $title = isset($item['track_title']) ? esc_html($item['track_title']) : '';
                    $url = isset($item['track_url']) ? esc_url($item['track_url']) : '';
                ?>
                    <li class="track-item type-<?php echo $type; ?>">
                        <?php 
                        // Logic: If it's a 'track' AND has a URL, make it a link.
                        // Otherwise (spacers or tracks without links), just show text.
                        if ($type === 'track' && !empty($url)) : ?>
                            <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo $title; ?>
                            </a>
                        <?php else : ?>
                            <span class="track-title"><?php echo $title; ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <div class="links"><?php wp_link_pages(); ?></div>
</div>
<!-- /entry-content.php -->