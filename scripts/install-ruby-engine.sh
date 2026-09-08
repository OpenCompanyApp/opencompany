#!/usr/bin/env bash
# Install one reviewed Bowerbird Ruby-engine RC without changing application config.
set -euo pipefail

readonly VERSION='v0.1.0-rc.1'
readonly SOURCE_REVISION='772e2b46c37a4bcf4cffbc57e6d9214493c11473'
readonly RELEASE_BASE="https://github.com/OpenCompanyApp/bowerbird-ruby-engine/releases/download/${VERSION}"

repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
install_root="${RUBY_ENGINE_INSTALL_ROOT:-${repo_root}/.runtime/ruby-engine}"

case "$(uname -s):$(uname -m)" in
  Darwin:arm64)
    platform='macos-arm64'
    archive_sha256='1b438c443fc88da4efd71ec3147acb8b3a30b0366c41612c07985f65351222b8'
    ;;
  Linux:x86_64)
    platform='linux-amd64'
    archive_sha256='3c4b1d629d4813b5ffa977fc1559844c58ea94ffd391a6ffce9e7ac0b016605c'
    ;;
  Linux:aarch64)
    platform='linux-arm64'
    archive_sha256='5d52067f4f25301925cdf92507fbadc11e987ac2c8ea3cd347ac81b7fd7691ad'
    ;;
  *)
    echo "Unsupported Ruby-engine platform: $(uname -s) $(uname -m)" >&2
    exit 1
    ;;
esac

archive="bowerbird-ruby-engine-${VERSION}-${platform}.tar.gz"
destination="${install_root}/${VERSION}/ruby-engine"

print_config() {
  echo "Installed source revision: ${SOURCE_REVISION}"
  echo "Set these in your local environment; this installer never writes .env:"
  echo "RUBY_ENGINE_BINARY=${destination}"
  echo "RUBY_ENGINE_SHA256=$(shasum -a 256 "${destination}" | awk '{print $1}')"
}

tmp_parent="${TMPDIR:-/tmp}"
tmp_dir="$(mktemp -d "${tmp_parent%/}/opencompany-ruby-engine.XXXXXX")"
cleanup() {
  case "${tmp_dir}" in
    "${tmp_parent%/}"/opencompany-ruby-engine.*)
      [ -d "${tmp_dir}" ] && rm -rf -- "${tmp_dir}"
      ;;
    *)
      echo "Refusing unsafe temporary cleanup target: ${tmp_dir}" >&2
      ;;
  esac
}
trap cleanup EXIT INT TERM

archive_path="${tmp_dir}/${archive}"
curl --fail --location --proto '=https' --tlsv1.2 --output "${archive_path}" "${RELEASE_BASE}/${archive}"
actual_archive_sha256="$(shasum -a 256 "${archive_path}" | awk '{print $1}')"
if [ "${actual_archive_sha256}" != "${archive_sha256}" ]; then
  echo "Ruby-engine archive checksum mismatch; refusing extraction." >&2
  exit 1
fi

member="bowerbird-ruby-engine-${VERSION}-${platform}/bin/ruby-engine"
if ! tar -tzf "${archive_path}" | grep -Fxq "${member}"; then
  echo "Verified archive is missing its expected engine path; refusing installation." >&2
  exit 1
fi
tar -xzf "${archive_path}" -C "${tmp_dir}" "${member}"
candidate="${tmp_dir}/${member}"
if [ ! -f "${candidate}" ] || [ ! -x "${candidate}" ]; then
  echo "Extracted Ruby engine is not an executable file." >&2
  exit 1
fi

mkdir -p "$(dirname -- "${destination}")"
if [ -e "${destination}" ]; then
  if [ ! -f "${destination}" ] || [ ! -x "${destination}" ]; then
    echo "Refusing existing non-executable target: ${destination}" >&2
    exit 1
  fi
  # Compare to the verified archive's exact binary. This makes a rerun
  # idempotent while refusing a different local executable without replacing it.
  if [ "$(shasum -a 256 "${candidate}" | awk '{print $1}')" = "$(shasum -a 256 "${destination}" | awk '{print $1}')" ]; then
    echo "Ruby engine already matches the verified ${VERSION} archive: ${destination}"
    print_config
    exit 0
  fi
  echo "Refusing to replace a different Ruby engine: ${destination}" >&2
  exit 1
fi
# Refuse a target created by a concurrent installer after the existence check.
# mv -n is supported by both the qualified macOS and Linux hosts.
expected_binary_sha256="$(shasum -a 256 "${candidate}" | awk '{print $1}')"
mv -n "${candidate}" "${destination}"
if [ "$(shasum -a 256 "${destination}" | awk '{print $1}')" != "${expected_binary_sha256}" ]; then
  echo "A different target appeared during installation; refusing to overwrite it." >&2
  exit 1
fi
chmod 0555 "${destination}"
print_config
