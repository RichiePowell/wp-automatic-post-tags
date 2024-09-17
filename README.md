# Automatic Post Tags

**Version:** 1.0  
**Author:** Rich Powell  
**License:** GPL2  
**Tested up to:** WordPress 6.6.2

## Description

The **Automatic Post Tags** plugin scans the content of posts and suggests or automatically adds relevant tags based on keywords found in the text. This helps users easily organize their posts with the right tags. The plugin provides an option to use AI (such as OpenAI's ChatGPT or other alternatives) or a built-in keyword extraction method.

### Features:

- **Automatic Tag Suggestions:** Scans post content for relevant keywords and suggests tags.
- **AI Integration:** Option to harness AI, such as OpenAI's ChatGPT, to generate more accurate tags.
- **Automatic Tag Addition:** Optionally, automatically adds tags to posts when they are saved.
- **Admin Settings:** Choose between AI or built-in keyword extraction methods.
- **Tag Integration in Editor:** Suggested tags appear above the "Most Used" tags in the Tags meta box for easy addition.

## Installation

1. Download the plugin and extract the ZIP file.
2. Upload the `automatic-post-tags` folder to your WordPress `/wp-content/plugins/` directory.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Go to **Settings > Automatic Post Tags** to configure your tag generation method.

## Configuration

After activation, navigate to **Settings > Automatic Post Tags** to configure:

- **Tag Generation Method:** Choose between `Built-in Keyword Extraction`, `ChatGPT`, or `Other AI Service`.
- **API Key (optional):** If you select an AI method, provide your API key for the AI service.
- **Automatically Add Tags:** Enable this option to automatically add suggested tags when a post is saved.

## Usage

1. **Create or Edit a Post:**

   - In the WordPress editor, you'll find the **Tags** meta box on the right side.
   - Suggested tags will appear above the "Most Used" tags section.

2. **Adding Suggested Tags:**

   - Click on any of the suggested tags to add them to your post.
   - The tag will automatically be added to your list of post tags.

3. **Refreshing Suggested Tags:**
   - If you've updated your post content and want new suggestions, click the **Refresh Tags** button in the **Suggested Tags** section.

## FAQ

### How does the built-in keyword extraction work?

The plugin uses a simple algorithm to analyze the post content, removing common stop words and then extracting the most frequent words to suggest as tags.

### How do I enable AI tag generation?

Go to **Settings > Automatic Post Tags**, select an AI method (e.g., ChatGPT), and enter your API key for the service. The plugin will then use AI to generate suggested tags based on the post content.

### Does the plugin automatically add tags?

Yes, if you enable the **Automatically Add Tags** option in the settings, the plugin will add tags to your posts when they are saved. You can also manually select tags in the post editor.

### Is the plugin compatible with custom post types?

Currently, the plugin is designed to work with regular posts. Future updates may include support for custom post types.

### Where can I get support?

For support, please visit the [GitHub repository](https://github.com/RichiePowell/wp-automatic-post-tags) or contact the author through [richpowell.co.uk](https://richpowell.co.uk).

## Screenshots

1. **Settings Page:** Configure the tag generation method and AI API key.
2. **Tags Meta Box:** Suggested tags appear above "Most Used" tags.
3. **Automatic Tagging:** Automatically add tags when saving a post.

## Changelog

### 1.0

- Initial release.

## License

This plugin is licensed under the GPL2. See the [LICENSE](LICENSE) file for details.

## Author

Developed by [Rich Powell](https://richpowell.co.uk).

- **GitHub:** [RichiePowell](https://github.com/RichiePowell/wp-automatic-post-tags)
