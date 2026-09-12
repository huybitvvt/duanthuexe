import {ethers} from "ethers";

export const providerBsc = () => {
    const NODE_URL = "https://bsc-dataseed.binance.org/"; // Mainnet
    // const NODE_URL = "https://nd-992-280-803.p2pify.com/d490684e453922023fef8062faf30e4d";
    return new ethers.providers.JsonRpcProvider(NODE_URL);

};

export default {providerBsc};
