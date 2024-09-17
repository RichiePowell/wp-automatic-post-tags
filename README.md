# Automatic Post Tags

**Version:** 1.0  
**Author:** Rich Powell  
**License:** GPL2  
**Tested up to:** WordPress 6.6.2

## Description

The **Automatic Post Tags** plugin scans the content of posts and suggests or automatically adds relevant tags based on keywords found in the text. This helps users easily organize their posts with the right tags. The plugin provides an option to use AI (such as OpenAI's GPT) or a built-in keyword extraction method.

### Features:

- **Automatic Tag Suggestions:** Scans post content for relevant keywords and suggests tags.
- **AI Integration:** Option to harness AI, such as OpenAI's GPT, to generate more accurate tags.
- **Automatic Tag Addition:** Optionally, automatically adds tags to posts when they are saved.
- **Admin Settings:** Choose between AI or built-in keyword extraction methods.
- **Tag Management in Editor:** Manually select or refresh suggested tags within the post editor.

## Installation

1. Download the plugin and extract the ZIP file.
2. Upload the plugin folder to your WordPress `/wp-content/plugins/` directory.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Go to **Settings > Automatic Post Tags** to configure your tag generation method.

## Configuration

After activation, navigate to **Settings > Automatic Post Tags** to configure:

- **Tag Generation Method:** Choose between `Built-in Keyword Extraction` or `AI (ChatGPT or other)`.
- **API Key (optional):** If you select the AI method, provide your API key for the AI service.
- **Automatically Add Tags:** Enable this option to automatically add suggested tags when a post is saved.

## Usage

1. Create or edit a post in the WordPress editor.
2. In the right-hand side, you’ll see a "Suggested Tags" box.
3. If tags are not generated automatically, click **Refresh Tags**.
4. Select the suggested tags you'd like to add to your post, or enable the **Automatically Add Tags** setting for the plugin to handle this step.

## FAQ

### How does the built-in keyword extraction work?

The plugin uses a simple algorithm to analyze the post content, removing common stop words and then extracting the most frequent words to suggest as tags.

### How do I enable AI tag generation?

Go to **Settings > Automatic Post Tags**, select the AI method, and enter your API key for a service such as OpenAI's GPT. The plugin will then use AI to generate suggested tags based on the post content.

### Does the plugin automatically add tags?

Yes, if you enable the **Automatically Add Tags** option in the settings, the plugin will add tags to your posts when they are saved. You can also manually select tags in the post editor.

### Is the plugin compatible with custom post types?

Currently, the plugin is designed to work with regular posts. However, it can be easily modified to support custom post types.

## Screenshots

1. **Settings Page**: Configure the tag generation method and AI API key.
2. **Post Editor Tags**: Suggested tags based on post content.
3. **Automatic Tagging**: Automatically add tags when saving a post.

## Changelog

### 1.0

- Initial release

## License

This plugin is licensed under the GPL2. See the [LICENSE](LICENSE) file for details.

## Support

For support, visit the [plugin support page](https://example.com/support) or raise an issue in the [GitHub repository](https://github.com/yourusername/automatic-post-tags).
