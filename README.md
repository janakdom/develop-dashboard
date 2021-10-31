# Development dashboard

This package is used to support local development on my computers. It contains a list of useful services and links in one place.

The application uses a simple router to show different pages. `example.org/name` will use a source from `pages/name.md`. The default route `/` is the alias to `index` with source in `pages/index.md` file. 

### Demo
[Sample static output](https://janakdom.github.io/develop-dashboard/) 
<sub>(Static page without routing)</sup>


### Special md-source tags
|     Tag     |   Rewrite   |
| ----------- | ----------- |
|`::br::` | &lt;br /&gt; |

### Warning
MD source may not contains HTML code!

## License
2021 - MIT - Dominik Janák