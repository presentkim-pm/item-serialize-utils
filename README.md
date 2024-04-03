<!-- PROJECT BADGES -->
<div align="center">

[![Poggit CI][poggit-ci-badge]][poggit-ci-url]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/item-serialize-utils/main/assets/icon.png" alt="Logo" width="80" height="80"/>
  <h3>item-serialize-utils</h3>
  <p align="center">
    Provides utils for (de)serialize more shorter and easier!

[View in Poggit][poggit-ci-url] · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

## About The Project

:heavy_check_mark: Provides classes for serialize item

- `kim\present\utils\itemserialize\ItemSerializeUtils`
- `kim\present\utils\itemserialize\SerializeMode`

:heavy_check_mark: Provides util function for serialize item

- `ItemSerializeUtils::serialize(Item $item, SerializeMode $mode = SerializeMode::BINARY) : string`

:heavy_check_mark: Provides util function for deserialize item

- `ItemSerializeUtils::deserialize(string $contents, SerializeMode $mode = SerializeMode::BINARY) : Item`

:heavy_check_mark: Provides multiple serialize modes

- `SerializeMode::BINARY` : Binary string that write by BigEndianNbtSerializer
- `SerializeMode::BASE64` : Same as BINARY, but encoded in base64_encode()
- `SerializeMode::HEX` : Same as BINARY, but encoded in bin2hex()
- `SerializeMode::SNBT ` : [Stringified Named Binary Tag](https://minecraft.fandom.com/wiki/NBT_format#SNBT_format)
  format string
- `SerializeMode::JSON` : JSON format string

> **NOTE** : JSON mode is not same as `Item::legacyJsonDeserialize()`

-----

## Installation

See [Official Poggit Virion Documentation](https://github.com/poggit/support/blob/master/virion.md)

-----

## How to use?

See [Main Document](https://github.com/presentkim-pm/item-serialize-utils/blob/main/docs/README.md)

-----

## License

Distributed under the **MIT**. See [LICENSE][license-url] for more information


[poggit-ci-badge]: https://poggit.pmmp.io/ci.shield/presentkim-pm/item-serialize-utils/item-serialize-utils?style=for-the-badge

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/item-serialize-utils.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/item-serialize-utils.svg?style=for-the-badge

[poggit-ci-url]: https://poggit.pmmp.io/ci/presentkim-pm/item-serialize-utils/item-serialize-utils

[stars-url]: https://github.com/presentkim-pm/item-serialize-utils/stargazers

[issues-url]: https://github.com/presentkim-pm/item-serialize-utils/issues

[license-url]: https://github.com/presentkim-pm/item-serialize-utils/blob/main/LICENSE

[project-icon]: https://raw.githubusercontent.com/presentkim-pm/item-serialize-utils/main/assets/icon.png
