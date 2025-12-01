// Certificado y clave privada por defecto (solo para propósitos de prueba)
const DEFAULT_CERTIFICATE = `-----BEGIN CERTIFICATE-----
MIIECzCCAvOgAwIBAgIGAZranflvMA0GCSqGSIb3DQEBCwUAMIGiMQswCQYDVQQG
EwJVUzELMAkGA1UECAwCTlkxEjAQBgNVBAcMCUNhbmFzdG90YTEbMBkGA1UECgwS
UVogSW5kdXN0cmllcywgTExDMRswGQYDVQQLDBJRWiBJbmR1c3RyaWVzLCBMTEMx
HDAaBgkqhkiG9w0BCQEWDXN1cHBvcnRAcXouaW8xGjAYBgNVBAMMEVFaIFRyYXkg
RGVtbyBDZXJ0MB4XDTI1MTEzMDE1NTI1N1oXDTQ1MTEzMDE1NTI1N1owgaIxCzAJ
BgNVBAYTAlVTMQswCQYDVQQIDAJOWTESMBAGA1UEBwwJQ2FuYXN0b3RhMRswGQYD
VQQKDBJRWiBJbmR1c3RyaWVzLCBMTEMxGzAZBgNVBAsMElFaIEluZHVzdHJpZXMs
IExMQzEcMBoGCSqGSIb3DQEJARYNc3VwcG9ydEBxei5pbzEaMBgGA1UEAwwRUVog
VHJheSBEZW1vIENlcnQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDD
IV2cCHDo+kH5HjClQBT2Akawx1FC/weuwTijjrObI9gAY9quYK1okcjKTyHX9vw/
/7YSAXG/GkisRWmbq4f12TWT/xSm/J7onnnjq+F3GWcozixckbYbQjpjROhkI4LO
YXnw5S0IVRr1BRKVugXTHEgiea0N6H7ydJ/tD3bNYyYfZ5WkmqlnKO4HO4ky3DVn
1Xh0etlkChtaye1N13gugWZshxcj2Tbm/3Th6JmToDzWyIWnK2MZShDAX32IPSV6
5VnYhpDFIaqzHcLa2I8FClmW/16TEZVf2hFoYMT0Kl5WgWkiH/RsP2f7abmnOZpA
ByKG9X1zG3FhPGEZkwsPAgMBAAGjRTBDMBIGA1UdEwEB/wQIMAYBAf8CAQEwDgYD
VR0PAQH/BAQDAgEGMB0GA1UdDgQWBBRQP8KTE9lKsQwyc4mltz7BVUAq2zANBgkq
hkiG9w0BAQsFAAOCAQEAK4gPBSaRnhWodqu5g9zFzEU5bBt9IwfHEUDsO2jFeJ3n
uCj75GuZxzvmjItJ7XnpuXCl721AfGDWCYVXwi9S3yIw8E1oczTPZHeLrybhw9UH
sth/NTzryx/Ip/0KLd2l2JCd9k8LmCVPyh+Z/HwBCMIKJmQjk7IIAmvvdURaP0xY
w0PZRfBHiUHrZfwDihw4UkusnMrjjDEfvraZ+RnmCqMebHRUP8sigAuGZwz82dJq
sQ+rab61wtaRfupoWa/aXeTAzzfO59mU7+dxvsfsbxctsZJotxMqemEQMObP3yUT
5YhgkQkDn5u9h9wAQ6mDsZzjvP41sMGaDSFhiz9l6g==
-----END CERTIFICATE-----`;

const DEFAULT_PRIVATE_KEY = `-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDDIV2cCHDo+kH5
HjClQBT2Akawx1FC/weuwTijjrObI9gAY9quYK1okcjKTyHX9vw//7YSAXG/Gkis
RWmbq4f12TWT/xSm/J7onnnjq+F3GWcozixckbYbQjpjROhkI4LOYXnw5S0IVRr1
BRKVugXTHEgiea0N6H7ydJ/tD3bNYyYfZ5WkmqlnKO4HO4ky3DVn1Xh0etlkChta
ye1N13gugWZshxcj2Tbm/3Th6JmToDzWyIWnK2MZShDAX32IPSV65VnYhpDFIaqz
HcLa2I8FClmW/16TEZVf2hFoYMT0Kl5WgWkiH/RsP2f7abmnOZpAByKG9X1zG3Fh
PGEZkwsPAgMBAAECggEAQfAKc+meTfwTQx1ijtTiwGbwgFg6K4uGixUcEJjuNGSe
XzNe+EIPFyD8WvD2nMYHY5EDc34tc8hr+lrSXxpNrVQi+MnfrrX69Nxoj/jLDbX1
2CIjd3x9ryRoGpd0eDJPx3HFBRRMbV5k55s5NoNP6JDMB2pagjKog0HJsQ/is3BP
9bxzuIANAoYY/963uDIBYAdUnnnvBqdO+qPNM58B0Q6xWcrZ/NdIWuU2puf/SZ2N
vpz79oe8n9KmO1tW11L8zuz0Ie7+LLFolsU/HbLaW2GwkUL+kMVJbyqsxQzXFPle
x2lKxl+JicRMEqThuE1LsS+HGWv1RTTn+2HrGnBi4QKBgQD2KWZSH+rEySt7P/uE
W9lKkRFDdkTgmXzJFnlBTcuBo26QF8wx7qHKFF1nf0fPChTTFFlFqNlSpnkfkbTW
4/kXia6g8Ljmddq7Cq5ZLzyqoH2xzaD8iBN3U3D19Q/q3LNgRy+vZL6yYzC4j4L9
ZgvwHZFak6xOyDBVGnk44qUOqQKBgQDK7dbMxD6jIvuI8znrj1hBcYTUW0hhbCEC
/crQHoP7WfBuVbLgIAwzh/ZxZeFiXb81Ly3ofhTO0WHsMD5hh8jrZyvSJDlmY4jP
tHt1kNw7htW2hpUEENIGyQQ9C8Tc7/60n7i4SYCb1rqCfWQ+7y+DoqpltJjjv2Zq
QSU/JRB29wKBgEWr4Ab8e6Eo4wKmUFTc/jpJpt42OjZrmtL7ZmRiVWgizqc/5Q54
4Rfl/a3Oa4+g5dX8W2wI50GqVnvl7I4pxhWwZVmt6FdqIfdwhXo+kWglto29ioQP
K5tJZZ5ntxKdVrO4UjnNROAOjPqfu85mtJhIdhxx0YIWzP85V/gOxfLJAoGAOmqx
NQ0hQvElG8141PjU3TICnOcSNQldj3Dj23mNYOQJNJny/lX7bTIsnYRIl3qJOpQ0
UQNKlibsW4Of0Y+3JRz0HnBTHch1b+VyzOtAmto712lyqFL3QwDG+ZPTvg5Qckqw
cEyoezQbSMkz/HH6aZiAGPseMCG/J8NNJ5pR3Q0CgYBOLk6JRdaY61rW8kKuT4WN
QMF1OG/w6FBaRBEzPn7o1XlcLkZZZRJyc0MfH6JriB0xAwB0mvYUJKYplX7JzHna
tGfahe213dm2eY0Eyh2wV4udlrptjdJ/LFY8tjxAIswMDJG9ba+uJE5JCBtwQIEF
GUj5WxRhrVjFSL/W/WyI2A==
-----END PRIVATE KEY-----`;

(function () {
  // Cargar Forge
  function loadForge() {
    return new Promise(function (resolve, reject) {
      if (window.forge) {
        resolve(window.forge); // Forge is already loaded
      } else {
        const s = document.createElement("script");
        s.src =
          "https://cdnjs.cloudflare.com/ajax/libs/forge/1.3.1/forge.min.js";
        s.onload = function () {
          resolve(window.forge); // Forge loaded successfully
        };
        s.onerror = function () {
          reject(new Error("Failed to load Forge library"));
        };
        document.head.appendChild(s);
      }
    });
  }

  async function configure() {
    try {
      const forge = await loadForge(); // Ensure forge is loaded before proceeding
      console.log("Forge loaded:", forge); // Check in the console if Forge is loaded correctly

      if (window.qz) {
        // Proceed with your signature setup here
        window.qz.security.setSignaturePromise(function (data) {
          return new Promise((resolve, reject) => {
            try {
              const pk = forge.pki.privateKeyFromPem(DEFAULT_PRIVATE_KEY);
              const md = forge.md.sha256.create();
              md.update(data, "utf8");
              const signature = forge.util.encode64(pk.sign(md));
              resolve(signature);
            } catch (e) {
              reject(e);
            }
          });
        });
      }
    } catch (e) {
      console.error("Error loading Forge:", e);
    }
  }

  configure();
})();
console.log("QZ Tray Available:", window.qz);
console.log("Certificate:", DEFAULT_CERTIFICATE);
console.log("Private Key:", DEFAULT_PRIVATE_KEY);
